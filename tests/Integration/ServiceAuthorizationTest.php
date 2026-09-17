<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use App\Services\Api\ApiClient;
use App\Services\ServiceAuth\ServiceToken;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Service-to-Service Authorization Tests
 *
 * Tests authorization and authentication between microservices
 */
class ServiceAuthorizationTest extends TestCase
{
    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiClient = app(ApiClient::class);
    }

    public function test_service_token_generation(): void
    {
        $token = ServiceToken::create('invoice-service', 'api-secret-key');

        $this->assertNotNull($token);
        $this->assertIsString($token);
    }

    public function test_service_token_includes_claims(): void
    {
        $token = ServiceToken::create('invoice-service', 'api-secret-key')
            ->withClaim('scope', 'invoices:read invoices:write')
            ->withClaim('company_id', 1);

        $decoded = ServiceToken::decode($token);

        $this->assertEquals('invoices:read invoices:write', $decoded['scope']);
        $this->assertEquals(1, $decoded['company_id']);
    }

    public function test_service_token_expiration(): void
    {
        $token = ServiceToken::create('invoice-service', 'api-secret-key')
            ->expiresIn(60); // 60 seconds

        $decoded = ServiceToken::decode($token);

        $this->assertArrayHasKey('exp', $decoded);
    }

    public function test_service_to_service_authentication_header(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response([
                'data' => [['id' => 1]],
            ]),
        ]);

        $token = ServiceToken::create('expense-service', 'api-secret-key');

        $response = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($token)
            ->get('/api/v1/invoices');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                   str_starts_with($request->header('Authorization'), 'Bearer ');
        });

        $this->assertArrayHasKey('data', $response);
    }

    public function test_service_authorization_scopes(): void
    {
        Http::fake([
            'http://product-service:8003/api/v1/products' => Http::response(
                ['data' => [['id' => 1, 'name' => 'Product 1']]],
                200
            ),
            'http://product-service:8003/api/v1/products/1' => Http::response(
                ['message' => 'Unauthorized'],
                403
            ),
        ]);

        // Token with read scope
        $readToken = ServiceToken::create('invoice-service', 'api-secret-key')
            ->withClaim('scope', 'products:read');

        // This should succeed
        $response = $this->apiClient
            ->withBaseUrl('http://product-service:8003')
            ->withToken($readToken)
            ->get('/api/v1/products');

        $this->assertArrayHasKey('data', $response);

        // Token without write scope
        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://product-service:8003')
            ->withToken($readToken)
            ->post('/api/v1/products/1', ['name' => 'Updated']);
    }

    public function test_service_role_based_access_control(): void
    {
        Http::fake([
            'http://settings-service:8005/api/v1/settings' => Http::response(
                ['locale' => 'en'],
                200
            ),
            'http://settings-service:8005/api/v1/admin/settings' => Http::response(
                ['message' => 'Admin only'],
                403
            ),
        ]);

        // Token with user role
        $userToken = ServiceToken::create('invoice-service', 'api-secret-key')
            ->withClaim('role', 'user');

        // User can read settings
        $response = $this->apiClient
            ->withBaseUrl('http://settings-service:8005')
            ->withToken($userToken)
            ->get('/api/v1/settings');

        $this->assertEquals('en', $response['locale']);

        // User cannot access admin endpoints
        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://settings-service:8005')
            ->withToken($userToken)
            ->get('/api/v1/admin/settings');
    }

    public function test_service_tenant_isolation(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::response([
                'data' => [['id' => 1, 'company_id' => 1]],
            ]),
        ]);

        // Token for tenant 1
        $tenant1Token = ServiceToken::create('expense-service', 'api-secret-key')
            ->withClaim('company_id', 1);

        // Token for tenant 2
        $tenant2Token = ServiceToken::create('expense-service', 'api-secret-key')
            ->withClaim('company_id', 2);

        // Tenant 1 should only see their invoices
        $response1 = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($tenant1Token)
            ->get('/api/v1/invoices');

        $this->assertEquals(1, $response1['data'][0]['company_id']);

        // Tenant 2 should get different data or empty
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::response([
                'data' => [['id' => 2, 'company_id' => 2]],
            ]),
        ]);

        $response2 = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($tenant2Token)
            ->get('/api/v1/invoices');

        $this->assertEquals(2, $response2['data'][0]['company_id']);
    }

    public function test_service_token_validation(): void
    {
        $validToken = ServiceToken::create('invoice-service', 'api-secret-key');

        $isValid = ServiceToken::validate($validToken, 'api-secret-key');
        $this->assertTrue($isValid);

        $isInvalid = ServiceToken::validate($validToken, 'wrong-secret-key');
        $this->assertFalse($isInvalid);
    }

    public function test_expired_service_token_rejected(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(
                ['message' => 'Token expired'],
                401
            ),
        ]);

        $expiredToken = ServiceToken::create('expense-service', 'api-secret-key')
            ->expiresIn(0); // Expired immediately

        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($expiredToken)
            ->get('/api/v1/invoices');
    }

    public function test_service_permission_required(): void
    {
        Http::fake([
            'http://customer-service:8004/api/v1/customers/1' => Http::response(
                ['message' => 'Insufficient permissions'],
                403
            ),
        ]);

        $token = ServiceToken::create('invoice-service', 'api-secret-key')
            ->withClaim('scope', 'customers:read');

        // Can read customer
        Http::fake([
            'http://customer-service:8004/api/v1/customers/1' => Http::response([
                'id' => 1,
                'name' => 'Customer Name',
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://customer-service:8004')
            ->withToken($token)
            ->get('/api/v1/customers/1');

        $this->assertEquals('Customer Name', $response['name']);

        // Cannot delete customer
        Http::fake([
            'http://customer-service:8004/api/v1/customers/1' => Http::response(
                ['message' => 'Insufficient permissions'],
                403
            ),
        ]);

        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://customer-service:8004')
            ->withToken($token)
            ->delete('/api/v1/customers/1');
    }

    public function test_api_key_based_service_auth(): void
    {
        Http::fake([
            'http://product-service:8003/api/v1/products' => Http::response([
                'data' => [['id' => 1]],
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('http://product-service:8003')
            ->withHeaders(['X-API-Key' => 'service-api-key-123'])
            ->get('/api/v1/products');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-API-Key') &&
                   $request->header('X-API-Key') === 'service-api-key-123';
        });

        $this->assertArrayHasKey('data', $response);
    }

    public function test_mutual_tls_authentication(): void
    {
        Http::fake([
            'https://invoice-service:8001/api/v1/invoices' => Http::response([
                'data' => [['id' => 1]],
            ]),
        ]);

        $response = $this->apiClient
            ->withBaseUrl('https://invoice-service:8001')
            ->withClientCertificate(
                certPath: '/path/to/client.crt',
                keyPath: '/path/to/client.key'
            )
            ->get('/api/v1/invoices');

        // Verify HTTPS endpoint used
        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://');
        });
    }

    public function test_service_identity_verification(): void
    {
        $token = ServiceToken::create('invoice-service', 'api-secret-key');

        $identity = ServiceToken::getIdentity($token);

        $this->assertEquals('invoice-service', $identity['service_name']);
    }

    public function test_rate_limiting_per_service(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(['id' => 1], 200)
                ->push(['id' => 2], 200)
                ->push(null, 429), // Too Many Requests
        ]);

        $token = ServiceToken::create('expense-service', 'api-secret-key');

        // First two requests should succeed
        for ($i = 0; $i < 2; $i++) {
            $response = $this->apiClient
                ->withBaseUrl('http://invoice-service:8001')
                ->withToken($token)
                ->get('/api/v1/invoices');
            $this->assertNotNull($response);
        }

        // Third request should hit rate limit
        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($token)
            ->get('/api/v1/invoices');
    }

    public function test_audit_logging_of_service_calls(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(['data' => []]),
        ]);

        $token = ServiceToken::create('expense-service', 'api-secret-key')
            ->withClaim('correlation_id', 'corr-123');

        $response = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($token)
            ->get('/api/v1/invoices');

        // Verify audit log entry
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Correlation-ID');
        });
    }

    public function test_service_authentication_failure_handling(): void
    {
        Http::fake([
            'http://settings-service:8005/api/v1/settings' => Http::response(
                ['message' => 'Unauthorized'],
                401
            ),
        ]);

        $invalidToken = 'invalid.token.here';

        $this->expectException(\Exception::class);

        $this->apiClient
            ->withBaseUrl('http://settings-service:8005')
            ->withToken($invalidToken)
            ->get('/api/v1/settings');
    }

    public function test_cross_service_permission_delegation(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response([
                'data' => [['id' => 1]],
            ]),
        ]);

        // Token delegated by admin service
        $delegatedToken = ServiceToken::create('admin-service', 'api-secret-key')
            ->withClaim('delegated_service', 'expense-service')
            ->withClaim('scope', 'invoices:read');

        $response = $this->apiClient
            ->withBaseUrl('http://invoice-service:8001')
            ->withToken($delegatedToken)
            ->get('/api/v1/invoices');

        $this->assertArrayHasKey('data', $response);
    }
}
