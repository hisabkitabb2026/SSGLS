<?php

declare(strict_types=1);

namespace App\Support\Resilience;

use Exception;
use Illuminate\Support\Facades\Redis;

/**
 * Exception thrown when a circuit breaker is open.
 */
class CircuitBreakerOpenException extends Exception {}

/**
 * Circuit Breaker implementation using Redis for distributed state management.
 *
 * Prevents cascading failures by tracking consecutive failures for a service.
 * Opens after 5 failures, then transitions to half-open after 60 seconds timeout
 * to allow recovery.
 *
 * States: CLOSED (normal) -> OPEN (failures exceeded) -> HALF_OPEN (testing recovery)
 *
 * @see https://martinfowler.com/bliki/CircuitBreaker.html
 */
class CircuitBreaker
{
    /**
     * Number of consecutive failures before opening the circuit.
     */
    private const FAILURE_THRESHOLD = 5;

    /**
     * Timeout in seconds before transitioning from open to half-open.
     */
    private const TIMEOUT_SECONDS = 60;

    /**
     * Circuit breaker states.
     */
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half-open';

    public function __construct(private string $service) {}

    /**
     * Check if circuit breaker is open.
     *
     * If timeout has elapsed since opening, transitions to half-open state
     * to allow test requests.
     *
     * @return bool True if circuit is open, false if closed or half-open
     */
    public function isOpen(): bool
    {
        $state = Redis::get($this->stateKey()) ?? self::STATE_CLOSED;

        if ($state !== self::STATE_OPEN) {
            return false;
        }

        // Check if timeout has passed
        $openedAt = Redis::get($this->openedAtKey());
        if ($openedAt === null) {
            return true;
        }

        if (time() - (int) $openedAt >= self::TIMEOUT_SECONDS) {
            // Transition to half-open state for testing recovery
            Redis::set($this->stateKey(), self::STATE_HALF_OPEN);

            return false;
        }

        return true;
    }

    /**
     * Record a successful call.
     *
     * Resets failure count and closes the circuit for normal operation.
     */
    public function recordSuccess(): void
    {
        Redis::del($this->failuresKey());
        Redis::del($this->openedAtKey());
        Redis::set($this->stateKey(), self::STATE_CLOSED);
    }

    /**
     * Record a failed call and throw if circuit should open.
     *
     * Increments failure counter. Throws exception if circuit is already open
     * or if failures reach the threshold.
     *
     * @throws CircuitBreakerOpenException
     */
    public function recordFailure(): void
    {
        // Reject if already open
        if ($this->isOpen()) {
            throw new CircuitBreakerOpenException(
                "Circuit breaker is open for service: {$this->service}"
            );
        }

        // Increment failure count
        $failures = (int) Redis::incr($this->failuresKey());

        if ($failures >= self::FAILURE_THRESHOLD) {
            // Open the circuit
            Redis::set($this->stateKey(), self::STATE_OPEN);
            Redis::set($this->openedAtKey(), time());

            throw new CircuitBreakerOpenException(
                "Circuit breaker opened for service: {$this->service} after {$failures} consecutive failures"
            );
        }
    }

    /**
     * Get the current state (closed, open, or half-open).
     */
    public function getState(): string
    {
        return Redis::get($this->stateKey()) ?? self::STATE_CLOSED;
    }

    /**
     * Reset the circuit breaker to closed state.
     */
    public function reset(): void
    {
        Redis::del([
            $this->stateKey(),
            $this->failuresKey(),
            $this->openedAtKey(),
        ]);
    }

    /**
     * Get the Redis key for circuit state.
     */
    private function stateKey(): string
    {
        return "circuit_breaker:{$this->service}:state";
    }

    /**
     * Get the Redis key for failure count.
     */
    private function failuresKey(): string
    {
        return "circuit_breaker:{$this->service}:failures";
    }

    /**
     * Get the Redis key for circuit open timestamp.
     */
    private function openedAtKey(): string
    {
        return "circuit_breaker:{$this->service}:opened_at";
    }
}
