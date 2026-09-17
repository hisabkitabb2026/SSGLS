<?php

declare(strict_types=1);

use App\Support\Resilience\CircuitBreaker;
use App\Support\Resilience\CircuitBreakerOpenException;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Mock Redis facade for circuit breaker tests
    $this->redis = [];

    Redis::shouldReceive('get')->andReturnUsing(function ($key) {
        return $this->redis[$key] ?? null;
    });

    Redis::shouldReceive('set')->andReturnUsing(function ($key, $value) {
        $this->redis[$key] = $value;
    });

    Redis::shouldReceive('incr')->andReturnUsing(function ($key) {
        if (! isset($this->redis[$key])) {
            $this->redis[$key] = 0;
        }

        return ++$this->redis[$key];
    });

    Redis::shouldReceive('del')->andReturnUsing(function ($keys) {
        if (is_array($keys)) {
            foreach ($keys as $key) {
                unset($this->redis[$key]);
            }
        } else {
            unset($this->redis[$keys]);
        }
    });
});

afterEach(function () {
    // Reset mocks
    Mockery::close();
});

test('recordSuccess() succeeds when circuit is closed', function () {
    $breaker = new CircuitBreaker('test_service');

    // Should not throw exception
    $breaker->recordSuccess();

    expect($breaker->getState())->toBe('closed');
});

test('recordFailure() throws exception after 5 failures', function () {
    $breaker = new CircuitBreaker('test_service');

    // Record 4 failures - should not throw yet
    for ($i = 0; $i < 4; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->getState())->toBe('closed');

    // 5th failure should throw and open the circuit
    expect(fn () => $breaker->recordFailure())
        ->toThrow(CircuitBreakerOpenException::class);

    expect($breaker->getState())->toBe('open');
});

test('throws CircuitBreakerOpenException when circuit is open', function () {
    $breaker = new CircuitBreaker('test_service');

    // Open the circuit by recording 5 failures
    for ($i = 0; $i < 5; $i++) {
        try {
            $breaker->recordFailure();
        } catch (CircuitBreakerOpenException) {
            // Expected on 5th failure
        }
    }

    // Any further calls should throw immediately
    expect(fn () => $breaker->recordFailure())
        ->toThrow(CircuitBreakerOpenException::class);

    expect(fn () => $breaker->recordFailure())
        ->toThrow(CircuitBreakerOpenException::class);
});

test('transitions to half-open state after 60 seconds timeout', function () {
    $breaker = new CircuitBreaker('test_service');

    // Open the circuit
    for ($i = 0; $i < 5; $i++) {
        try {
            $breaker->recordFailure();
        } catch (CircuitBreakerOpenException) {
            // Expected on 5th failure
        }
    }

    expect($breaker->getState())->toBe('open');
    expect($breaker->isOpen())->toBeTrue();

    // Simulate 60 seconds passing by setting openedAt to 61 seconds ago
    $openedAtKey = 'circuit_breaker:test_service:opened_at';
    Redis::set($openedAtKey, (int) time() - 61);

    // Circuit should now be in half-open state
    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->getState())->toBe('half-open');
});

test('closes circuit on success after half-open state', function () {
    $breaker = new CircuitBreaker('test_service');

    // Open the circuit
    for ($i = 0; $i < 5; $i++) {
        try {
            $breaker->recordFailure();
        } catch (CircuitBreakerOpenException) {
            // Expected on 5th failure
        }
    }

    expect($breaker->getState())->toBe('open');

    // Transition to half-open by simulating timeout
    $openedAtKey = 'circuit_breaker:test_service:opened_at';
    Redis::set($openedAtKey, (int) time() - 61);

    // Call isOpen() to trigger state transition to half-open
    expect($breaker->isOpen())->toBeFalse();
    expect($breaker->getState())->toBe('half-open');

    // Record success - should close the circuit
    $breaker->recordSuccess();

    expect($breaker->getState())->toBe('closed');
    expect($breaker->isOpen())->toBeFalse();
});

test('resets circuit state completely', function () {
    $breaker = new CircuitBreaker('test_service');

    // Open the circuit
    for ($i = 0; $i < 5; $i++) {
        try {
            $breaker->recordFailure();
        } catch (CircuitBreakerOpenException) {
            // Expected on 5th failure
        }
    }

    expect($breaker->getState())->toBe('open');

    // Reset the circuit
    $breaker->reset();

    expect($breaker->getState())->toBe('closed');
    expect($breaker->isOpen())->toBeFalse();
});

test('failure count increments correctly', function () {
    $breaker = new CircuitBreaker('test_service');

    // Record 3 failures
    for ($i = 0; $i < 3; $i++) {
        $breaker->recordFailure();
    }

    expect($breaker->getState())->toBe('closed');

    // Reset and verify state is truly closed
    $breaker->reset();
    expect($breaker->getState())->toBe('closed');

    // Can record success on fresh closed circuit
    $breaker->recordSuccess();
    expect($breaker->getState())->toBe('closed');
});
