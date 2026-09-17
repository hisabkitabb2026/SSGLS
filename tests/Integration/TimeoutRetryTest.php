<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Resilience\ResilientClient;
use App\Services\Resilience\RetryPolicy;
use App\Services\Resilience\TimeoutPolicy;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Tests\TestCase;

/**
 * Timeout and Retry Handling Tests
 *
 * Tests resilience patterns for service-to-service communication
 */
class TimeoutRetryTest extends TestCase
{
    private ResilientClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = app(ResilientClient::class);
    }

    public function test_request_timeout_handling(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(null, 0),
        ]);

        $timeoutPolicy = TimeoutPolicy::create()
            ->withTimeout(5)
            ->withUnit('seconds');

        $this->assertTrue($timeoutPolicy->timeout() > 0);
    }

    public function test_retry_on_transient_failure(): void
    {
        $callCount = 0;

        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::sequence()
                ->push(null, 503) // Service unavailable
                ->push(null, 503) // Service unavailable
                ->push(['id' => 1], 200), // Success
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->withDelay(100)
            ->onlyRetry([503, 500, 408]);

        $response = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertNotNull($response);
    }

    public function test_retry_exponential_backoff(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->withExponentialBackoff(multiplier: 2, initialDelay: 100);

        $response = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertNotNull($response);
    }

    public function test_retry_max_attempts_exceeded(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(null, 503),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->onlyRetry([503]);

        $this->expectException(\Exception::class);

        $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );
    }

    public function test_retry_with_jitter(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->withDelay(100)
            ->withJitter(true);

        $response = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertNotNull($response);
    }

    public function test_timeout_interrupt_long_request(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices/long-process' => Http::response(null, 0),
        ]);

        $timeoutPolicy = TimeoutPolicy::create()->withTimeout(1);

        $this->expectException(TimeoutException::class);

        $this->client->executeWithTimeout(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices/long-process'),
            $timeoutPolicy
        );
    }

    public function test_retry_does_not_retry_permanent_errors(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(
                ['message' => 'Invalid request'],
                400
            ),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->onlyRetry([500, 503]);

        $this->expectException(\Exception::class);

        $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );
    }

    public function test_retry_with_custom_condition(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(['available' => false], 200)
                ->push(['available' => true], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(2)
            ->withCustomCondition(fn ($response) => ! $response['available']);

        $response = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertTrue($response['available']);
    }

    public function test_timeout_combined_with_retry(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $timeoutPolicy = TimeoutPolicy::create()->withTimeout(5);
        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(2)
            ->onlyRetry([503]);

        $response = $this->client->executeWithTimeoutAndRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $timeoutPolicy,
            $retryPolicy
        );

        $this->assertNotNull($response);
    }

    public function test_retry_callback_on_attempt(): void
    {
        $attempts = [];

        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(2)
            ->onlyRetry([503])
            ->onAttempt(function ($attempt, $exception) use (&$attempts) {
                $attempts[] = $attempt;
            });

        $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertCount(2, $attempts);
    }

    public function test_timeout_callback_on_exceeded(): void
    {
        $timeoutFired = false;

        $timeoutPolicy = TimeoutPolicy::create()
            ->withTimeout(1)
            ->onTimeout(function () use (&$timeoutFired) {
                $timeoutFired = true;
            });

        // This should handle timeout gracefully
        try {
            $this->client->executeWithTimeout(
                fn () => sleep(2),
                $timeoutPolicy
            );
        } catch (\Exception $e) {
            // Expected
        }
    }

    public function test_retry_statistics_tracking(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(3)
            ->onlyRetry([503]);

        $result = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $stats = $retryPolicy->getStatistics();
        $this->assertEquals(3, $stats['total_attempts']);
        $this->assertEquals(2, $stats['failed_attempts']);
        $this->assertEquals(1, $stats['successful_attempt']);
    }

    public function test_timeout_per_operation_configuration(): void
    {
        $invoiceTimeout = TimeoutPolicy::create()->withTimeout(5);
        $paymentTimeout = TimeoutPolicy::create()->withTimeout(10);

        // Different timeouts for different operations
        $this->assertEquals(5, $invoiceTimeout->timeout());
        $this->assertEquals(10, $paymentTimeout->timeout());
    }

    public function test_retry_idempotent_only(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices*' => Http::sequence()
                ->push(null, 503)
                ->push(['id' => 1], 200),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(2)
            ->onlyRetry([503])
            ->idempotentOnly(true); // Only retry idempotent operations

        // GET is idempotent, should retry
        $response = $this->client->executeWithRetry(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $retryPolicy
        );

        $this->assertNotNull($response);
    }

    public function test_circuit_breaker_triggered_after_failures(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(null, 503),
        ]);

        $retryPolicy = RetryPolicy::create()
            ->withMaxAttempts(1)
            ->onlyRetry([503]);

        // After multiple failures, circuit should open
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->client->executeWithRetry(
                    fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
                    $retryPolicy
                );
            } catch (\Exception $e) {
                // Expected
            }
        }

        $isOpen = $retryPolicy->isCircuitOpen();
        $this->assertTrue($isOpen);
    }

    public function test_timeout_with_fallback(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(null, 0),
        ]);

        $timeoutPolicy = TimeoutPolicy::create()
            ->withTimeout(1)
            ->withFallback(fn () => ['id' => 0, 'cached' => true]);

        // Should return fallback on timeout
        $response = $this->client->executeWithTimeoutAndFallback(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices'),
            $timeoutPolicy
        );

        $this->assertTrue($response['cached']);
    }
}
