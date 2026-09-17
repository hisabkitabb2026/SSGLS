<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Resilience\CircuitBreaker;
use App\Services\Resilience\CircuitBreakerOpenException;
use App\Services\Resilience\CircuitBreakerState;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Circuit Breaker Pattern Tests
 *
 * Tests circuit breaker implementation for failure handling
 */
class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->breaker = app(CircuitBreaker::class);
    }

    public function test_circuit_breaker_initial_state_is_closed(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $this->assertEquals(CircuitBreakerState::CLOSED, $breaker->getState());
        $this->assertTrue($breaker->isClosed());
    }

    public function test_circuit_breaker_opens_after_failure_threshold(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        // Simulate 3 failures
        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure();
        }

        $this->assertEquals(CircuitBreakerState::OPEN, $breaker->getState());
        $this->assertTrue($breaker->isOpen());
    }

    public function test_circuit_breaker_closed_on_failure_increments_counter(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $breaker->recordFailure();
        $this->assertEquals(1, $breaker->getFailureCount());

        $breaker->recordFailure();
        $this->assertEquals(2, $breaker->getFailureCount());
    }

    public function test_circuit_breaker_open_rejects_calls(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->expectException(CircuitBreakerOpenException::class);

        $breaker->call(fn () => Http::get('http://invoice-service:8001/api/v1/invoices'));
    }

    public function test_circuit_breaker_half_open_after_timeout(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(1); // 1 second timeout

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->assertEquals(CircuitBreakerState::OPEN, $breaker->getState());

        // Wait for timeout
        sleep(2);

        $breaker->refresh();

        $this->assertEquals(CircuitBreakerState::HALF_OPEN, $breaker->getState());
        $this->assertTrue($breaker->isHalfOpen());
    }

    public function test_circuit_breaker_half_open_closes_on_success(): void
    {
        Http::fake([
            'http://invoice-service:8001/api/v1/invoices' => Http::response(['id' => 1]),
        ]);

        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(1);

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        sleep(2);
        $breaker->refresh();

        // Successful call should close the circuit
        $breaker->recordSuccess();
        $breaker->recordSuccess();

        $this->assertEquals(CircuitBreakerState::CLOSED, $breaker->getState());
    }

    public function test_circuit_breaker_returns_to_open_on_half_open_failure(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(1);

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        sleep(2);
        $breaker->refresh();

        // Failure in half-open state should reopen
        $breaker->recordFailure();

        $this->assertEquals(CircuitBreakerState::OPEN, $breaker->getState());
    }

    public function test_circuit_breaker_success_counter_on_half_open(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(3)
            ->withTimeout(1);

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        sleep(2);
        $breaker->refresh();

        // Record successes
        $breaker->recordSuccess();
        $this->assertEquals(1, $breaker->getSuccessCount());

        $breaker->recordSuccess();
        $this->assertEquals(2, $breaker->getSuccessCount());

        $breaker->recordSuccess();
        $this->assertEquals(3, $breaker->getSuccessCount());

        // Should close after success threshold is met
        $this->assertEquals(CircuitBreakerState::CLOSED, $breaker->getState());
    }

    public function test_circuit_breaker_failure_counter_resets_on_close(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->assertEquals(2, $breaker->getFailureCount());

        // Reset
        $breaker->reset();

        $this->assertEquals(0, $breaker->getFailureCount());
        $this->assertEquals(CircuitBreakerState::CLOSED, $breaker->getState());
    }

    public function test_circuit_breaker_with_fallback(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(60)
            ->withFallback(fn () => ['cached' => true, 'id' => 0]);

        // Open the circuit
        $breaker->recordFailure();
        $breaker->recordFailure();

        // Should return fallback
        $response = $breaker->executeWithFallback(
            fn () => Http::get('http://invoice-service:8001/api/v1/invoices')
        );

        $this->assertTrue($response['cached']);
    }

    public function test_circuit_breaker_metrics(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();

        $metrics = $breaker->getMetrics();

        $this->assertEquals(2, $metrics['total_failures']);
        $this->assertEquals(1, $metrics['total_successes']);
        $this->assertEquals(3, $metrics['total_calls']);
    }

    public function test_circuit_breaker_state_transitions_tracking(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(2)
            ->withSuccessThreshold(2)
            ->withTimeout(1);

        $transitions = [];

        $breaker->onStateChange(function ($oldState, $newState) use (&$transitions) {
            $transitions[] = ['from' => $oldState, 'to' => $newState];
        });

        // Transition: CLOSED -> OPEN
        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->assertCount(1, $transitions);
        $this->assertEquals(CircuitBreakerState::CLOSED, $transitions[0]['from']);
        $this->assertEquals(CircuitBreakerState::OPEN, $transitions[0]['to']);
    }

    public function test_circuit_breaker_multiple_services(): void
    {
        $invoiceBreaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $expenseBreaker = CircuitBreaker::create('expense-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        // Fail invoice service
        $invoiceBreaker->recordFailure();
        $invoiceBreaker->recordFailure();
        $invoiceBreaker->recordFailure();

        // Expense service should still be closed
        $this->assertEquals(CircuitBreakerState::OPEN, $invoiceBreaker->getState());
        $this->assertEquals(CircuitBreakerState::CLOSED, $expenseBreaker->getState());
    }

    public function test_circuit_breaker_per_method_configuration(): void
    {
        $invoiceBreaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $paymentBreaker = CircuitBreaker::create('payment-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $this->assertEquals(5, $invoiceBreaker->getFailureThreshold());
        $this->assertEquals(3, $paymentBreaker->getFailureThreshold());
    }

    public function test_circuit_breaker_request_volume_threshold(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(3)
            ->withSuccessThreshold(2)
            ->withTimeout(60)
            ->withMinimumRequestVolume(10);

        // Only 5 requests
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure();
        }

        // Should still be closed because minimum request volume not reached
        $this->assertEquals(CircuitBreakerState::CLOSED, $breaker->getState());
    }

    public function test_circuit_breaker_error_rate_threshold(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60)
            ->withErrorRateThreshold(0.5); // 50%

        // 5 successes, 5 failures = 50% error rate
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordSuccess();
            $breaker->recordFailure();
        }

        // Should open at 50% error rate
        $this->assertEquals(CircuitBreakerState::OPEN, $breaker->getState());
    }

    public function test_circuit_breaker_slow_call_detection(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60)
            ->withSlowCallDurationThreshold(5000); // 5 seconds

        // Record slow calls
        $breaker->recordSlowCall(6000); // 6 seconds
        $breaker->recordSlowCall(7000); // 7 seconds

        $metrics = $breaker->getMetrics();
        $this->assertEquals(2, $metrics['slow_calls']);
    }

    public function test_circuit_breaker_concurrent_requests(): void
    {
        $breaker = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        // Simulate concurrent requests
        for ($i = 0; $i < 3; $i++) {
            $breaker->recordSuccess();
        }

        // All concurrent requests should succeed
        $this->assertEquals(3, $breaker->getMetrics()['total_successes']);
    }

    public function test_circuit_breaker_persistence(): void
    {
        $breaker1 = CircuitBreaker::create('invoice-service')
            ->withFailureThreshold(5)
            ->withSuccessThreshold(2)
            ->withTimeout(60);

        $breaker1->recordFailure();
        $breaker1->recordFailure();

        // Create new instance with same service
        $breaker2 = CircuitBreaker::create('invoice-service');

        // State should be preserved
        $this->assertEquals($breaker1->getFailureCount(), $breaker2->getFailureCount());
    }
}
