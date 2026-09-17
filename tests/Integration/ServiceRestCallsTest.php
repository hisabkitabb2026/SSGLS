<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Service REST Calls Integration Tests
 *
 * Tests REST communication between microservices
 */
class ServiceRestCallsTest extends TestCase
{
    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiClient = app(ApiClient::class);
    }

    public function test_make_rest_call_to_service(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/health' => Http::response([
                'status' => 'healthy',
                'service' => 'invoice-service',
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->get('/api/v1/health');

        $this->assertEquals('healthy', $response['status']);
        $this->assertEquals('invoice-service', $response['service']);
    }

    public function test_service_rest_call_with_headers(): void
    {
        Http::fake([
            'http://expense-service:8002/api/v1/expenses' => Http::response([
                'data' => ['id' => 1, 'amount' => 100],
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://expense-service:8002')
            ->withToken('test-token-123')
            ->get('/api/v1/expenses');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                   $request->header('Authorization') === 'Bearer test-token-123';
        });

        $this->assertArrayHasKey('data', $response);
    }

    public function test_service_post_request(): void
    {
        Http::fake([
            'http://product-service:8003/api/v1/products' => Http::response([
                'id' => 1,
                'name' => 'Product 1',
                'created_at' => now()->toIso8601String(),
            ], 201),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://product-service:8003')
            ->post('/api/v1/products', [
                'name' => 'Product 1',
                'price' => 99.99,
            ]);

        $this->assertEquals('Product 1', $response['name']);
        $this->assertArrayHasKey('created_at', $response);
    }

    public function test_service_patch_request(): void
    {
        Http::fake([
            'http://customer-service:8004/api/v1/customers/1' => Http::response([
                'id' => 1,
                'name' => 'Updated Customer',
                'email' => 'customer@example.com',
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://customer-service:8004')
            ->patch('/api/v1/customers/1', [
                'name' => 'Updated Customer',
            ]);

        $this->assertEquals('Updated Customer', $response['name']);
    }

    public function test_service_delete_request(): void
    {
        Http::fake([
            'http://settings-service:8005/api/v1/settings/1' => Http::response(null, 204),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://settings-service:8005')
            ->delete('/api/v1/settings/1');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), '/api/v1/settings/1');
        });
    }

    public function test_rest_call_with_query_parameters(): void
    {
        Http::fake([
            'http://transport-service:8006/api/v1/shipments*' => Http::response([
                'data' => [
                    ['id' => 1, 'status' => 'pending'],
                    ['id' => 2, 'status' => 'pending'],
                ],
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://transport-service:8006')
            ->get('/api/v1/shipments', ['status' => 'pending']);

        $this->assertCount(2, $response['data']);
    }

    public function test_service_to_service_error_handling(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices/999' => Http::response([
                'message' => 'Invoice not found',
            ], 404),
        ]);

        $this->expectException(ApiException::class);

        $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->get('/api/v1/invoices/999');
    }

    public function test_service_rest_call_response_format(): void
    {
        Http::fake([
            'http://expense-service:8002/api/v1/expenses' => Http::response([
                'data' => [
                    ['id' => 1, 'amount' => 100],
                    ['id' => 2, 'amount' => 200],
                ],
                'meta' => [
                    'total' => 2,
                    'per_page' => 15,
                    'current_page' => 1,
                ],
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://expense-service:8002')
            ->get('/api/v1/expenses');

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertIsArray($response['data']);
    }

    public function test_service_chained_rest_calls(): void
    {
        // Simulate: Get customer -> Get customer's invoices -> Get invoice details
        Http::fake([
            'http://customer-service:8004/api/v1/customers/1' => Http::response([
                'id' => 1,
                'name' => 'Test Customer',
            ]),
            'http://invoice-service:8001/api/v1/invoices*' => Http::response([
                'data' => [['id' => 101, 'customer_id' => 1]],
            ]),
            'http://invoice-service:8001/api/v1/invoices/101' => Http::response([
                'id' => 101,
                'customer_id' => 1,
                'amount' => 500.00,
            ]),
        ]);

        // Get customer
        $customer = $this->apiClient
            ->withBaseUrl('http://customer-service:8004')
            ->get('/api/v1/customers/1');

        $this->assertEquals(1, $customer['id']);

        // Get customer's invoices
        $invoices = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->get('/api/v1/invoices', ['customer_id' => 1]);

        $this->assertCount(1, $invoices['data']);

        // Get invoice details
        $invoice = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->get('/api/v1/invoices/101');

        $this->assertEquals(500.00, $invoice['amount']);
    }

    public function test_service_rest_call_json_serialization(): void
    {
        $payload = [
            'name' => 'Test Product',
            'description' => 'A test product',
            'price' => 99.99,
            'metadata' => [
                'sku' => 'TEST-001',
                'category' => 'electronics',
            ],
        ];

        Http::fake([
            'http://product-service:8003/api/v1/products' => Http::response(
                array_merge(['id' => 1], $payload),
                201
            ),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://product-service:8003')
            ->post('/api/v1/products', $payload);

        Http::assertSent(function ($request) use ($payload) {
            return json_decode($request->body(), true) === $payload;
        });

        $this->assertEquals('Test Product', $response['name']);
        $this->assertArrayHasKey('metadata', $response);
    }

    public function test_service_rest_call_with_timeout(): void
    {
        $apiClient = new ApiClient(
            baseUrl: 'http://invoice-service:8001',
            timeout: 5,
        );

        // Verify timeout is set
        $this->assertEquals(5, $apiClient->getTimeout());
    }

    public function test_service_rest_call_batch_requests(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(['id' => 1, 'amount' => 100], 200)
                ->push(['id' => 2, 'amount' => 200], 200)
                ->push(['id' => 3, 'amount' => 300], 200),
        ]);

        $apiClient = $this->apiClient->withBaseUrl('http://invoice-service:8001');

        for ($i = 1; $i <= 3; $i++) {
            $response = $apiClient->get("/api/v1/invoices/{$i}");
            $this->assertEquals($i, $response['id']);
        }
    }

    public function test_service_rest_call_with_custom_headers(): void
    {
        Http::fake([
            'http://settings-service:8005/api/v1/settings' => Http::response([
                'locale' => 'en',
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://settings-service:8005')
            ->withHeaders(['X-Request-ID' => 'req-123'])
            ->get('/api/v1/settings');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Request-ID');
        });
    }

    public function test_service_rest_call_empty_response(): void
    {
        Http::fake([
            'http://transport-service:8006/api/v1/shipments/1' => Http::response(null, 204),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://transport-service:8006')
            ->delete('/api/v1/shipments/1');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE';
        });
    }
}
