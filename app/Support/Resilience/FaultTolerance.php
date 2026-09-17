<?php

declare(strict_types=1);

namespace App\Support\Resilience;

use Closure;
use Exception;

/**
 * Fault Tolerance Facade: Combines Circuit Breaker + Retry + Timeout patterns.
 *
 * Execution order: Circuit Breaker -> Retry -> Timeout -> Closure
 * - Circuit breaker: Prevents cascading failures across service boundaries
 * - Retry: Handles transient failures with exponential backoff
 * - Timeout: Prevents indefinite waiting with SIGALRM
 */
class FaultTolerance
{
    private CircuitBreaker $circuitBreaker;

    private RetryPolicy $retryPolicy;

    private int $timeoutSeconds;

    public function __construct(
        string $serviceName,
        ?RetryPolicy $retryPolicy = null,
        ?CircuitBreaker $circuitBreaker = null,
        int $timeoutSeconds = 30
    ) {
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker($serviceName);
        $this->retryPolicy = $retryPolicy ?? RetryPolicy::exponentialBackoff();
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Execute a closure with circuit breaker, retry, and timeout protection.
     *
     * Layering (outermost to innermost):
     * 1. Circuit breaker: throws if open, records success/failure
     * 2. Retry policy: attempts with exponential backoff on transient failures
     * 3. Timeout: enforces time limit using SIGALRM or falls back gracefully
     *
     * @param  Closure  $closure  The operation to execute
     * @return mixed Result from closure
     *
     * @throws CircuitBreakerOpenException When circuit is open
     * @throws Exception When all retries exhausted or timeout occurs
     */
    public function execute(Closure $closure)
    {
        // Check circuit breaker first (fail fast)
        if ($this->circuitBreaker->isOpen()) {
            throw new CircuitBreakerOpenException(
                'Circuit breaker is open. Service unavailable.'
            );
        }

        try {
            // Layer: Retry -> Timeout -> Closure
            $result = $this->retryPolicy->execute(function () use ($closure) {
                return $this->withTimeout($closure);
            });

            // Success: reset failure tracking
            $this->circuitBreaker->recordSuccess();

            return $result;
        } catch (Exception $e) {
            // Failure: record and possibly open circuit
            try {
                $this->circuitBreaker->recordFailure();
            } catch (CircuitBreakerOpenException $breaker) {
                throw $breaker;
            }

            // Re-throw original exception
            throw $e;
        }
    }

    /**
     * Wrap closure execution with timeout protection.
     */
    private function withTimeout(Closure $closure)
    {
        if (! Timeout::isSupported()) {
            return $closure();
        }

        return Timeout::execute($closure, $this->timeoutSeconds);
    }

    public static function make(
        string $serviceName,
        ?RetryPolicy $retryPolicy = null,
        int $timeoutSeconds = 30
    ): self {
        return new self($serviceName, $retryPolicy, null, $timeoutSeconds);
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }

    public function getRetryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    public function resetCircuitBreaker(): void
    {
        $this->circuitBreaker->reset();
    }
}
