<?php

declare(strict_types=1);

use App\Support\Resilience\RetryPolicy;

test('retries on failure and throws after max attempts', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;
        throw new Exception('Transient failure');
    };

    $policy = new RetryPolicy(maxAttempts: 3);

    expect(fn () => $policy->execute($closure))
        ->toThrow(Exception::class, 'Transient failure');

    expect($attempts)->toBe(3);
});

test('returns value on successful execution', function () {
    $closure = fn () => 'success';

    $policy = new RetryPolicy(maxAttempts: 3);
    $result = $policy->execute($closure);

    expect($result)->toBe('success');
});

test('succeeds after transient failures', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;
        if ($attempts < 3) {
            throw new Exception('Transient failure');
        }

        return 'success';
    };

    $policy = new RetryPolicy(maxAttempts: 5);
    $result = $policy->execute($closure);

    expect($result)->toBe('success');
    expect($attempts)->toBe(3);
});

test('exponential backoff generates correct delays', function () {
    $policy = RetryPolicy::exponentialBackoff(baseDelay: 1, maxAttempts: 5);

    $reflection = new ReflectionClass($policy);
    $backoffDelaysProperty = $reflection->getProperty('backoffDelays');
    $backoffDelaysProperty->setAccessible(true);
    $delays = $backoffDelaysProperty->getValue($policy);

    // Exponential backoff: 1, 2, 4, 8, 16 (each power of 2)
    expect($delays)->toBe([1, 2, 4, 8, 16]);
});

test('exponential backoff with different base delay', function () {
    $policy = RetryPolicy::exponentialBackoff(baseDelay: 2, maxAttempts: 4);

    $reflection = new ReflectionClass($policy);
    $backoffDelaysProperty = $reflection->getProperty('backoffDelays');
    $backoffDelaysProperty->setAccessible(true);
    $delays = $backoffDelaysProperty->getValue($policy);

    // Exponential backoff with base 2: 2, 4, 8, 16
    expect($delays)->toBe([2, 4, 8, 16]);
});

test('linear backoff generates equal delays', function () {
    $policy = RetryPolicy::linear(baseDelay: 2, maxAttempts: 4);

    $reflection = new ReflectionClass($policy);
    $backoffDelaysProperty = $reflection->getProperty('backoffDelays');
    $backoffDelaysProperty->setAccessible(true);
    $delays = $backoffDelaysProperty->getValue($policy);

    // Linear backoff: same delay for all attempts
    expect($delays)->toBe([2, 2, 2, 2]);
});

test('immediate backoff has zero delays', function () {
    $policy = RetryPolicy::immediate(maxAttempts: 3);

    $reflection = new ReflectionClass($policy);
    $backoffDelaysProperty = $reflection->getProperty('backoffDelays');
    $backoffDelaysProperty->setAccessible(true);
    $delays = $backoffDelaysProperty->getValue($policy);

    // Immediate: no delay
    expect($delays)->toBe([0, 0, 0]);
});

test('jitter applies variation to delays', function () {
    $policy = new RetryPolicy(maxAttempts: 5, backoffDelays: [10], useJitter: true);

    $reflection = new ReflectionClass($policy);
    $getDelayMethod = $reflection->getMethod('getDelay');
    $getDelayMethod->setAccessible(true);

    // Collect multiple samples to verify jitter is applied
    $delays = [];
    for ($i = 0; $i < 20; $i++) {
        $delays[] = $getDelayMethod->invoke($policy, 0);
    }

    // Base delay is 10, jitter is ±20% = ±2
    // Expected range: [8, 12]
    $minDelay = min($delays);
    $maxDelay = max($delays);

    expect($minDelay)->toBeGreaterThanOrEqual(8);
    expect($maxDelay)->toBeLessThanOrEqual(12);

    // Verify we got some variation (not all the same)
    expect(count(array_unique($delays)))->toBeGreaterThan(1);
});

test('jitter keeps delay non-negative', function () {
    $policy = new RetryPolicy(maxAttempts: 5, backoffDelays: [1], useJitter: true);

    $reflection = new ReflectionClass($policy);
    $getDelayMethod = $reflection->getMethod('getDelay');
    $getDelayMethod->setAccessible(true);

    // Collect samples with a small base delay where negative + jitter could go below 0
    for ($i = 0; $i < 50; $i++) {
        $delay = $getDelayMethod->invoke($policy, 0);
        expect($delay)->toBeGreaterThanOrEqual(0);
    }
});

test('no jitter returns exact base delay', function () {
    $policy = new RetryPolicy(maxAttempts: 5, backoffDelays: [5, 10, 15], useJitter: false);

    $reflection = new ReflectionClass($policy);
    $getDelayMethod = $reflection->getMethod('getDelay');
    $getDelayMethod->setAccessible(true);

    expect($getDelayMethod->invoke($policy, 0))->toBe(5);
    expect($getDelayMethod->invoke($policy, 1))->toBe(10);
    expect($getDelayMethod->invoke($policy, 2))->toBe(15);
});

test('uses last delay when attempt index exceeds delays array', function () {
    $policy = new RetryPolicy(maxAttempts: 5, backoffDelays: [1, 2, 4], useJitter: false);

    $reflection = new ReflectionClass($policy);
    $getDelayMethod = $reflection->getMethod('getDelay');
    $getDelayMethod->setAccessible(true);

    // Attempts 0, 1, 2 have defined delays
    expect($getDelayMethod->invoke($policy, 0))->toBe(1);
    expect($getDelayMethod->invoke($policy, 1))->toBe(2);
    expect($getDelayMethod->invoke($policy, 2))->toBe(4);

    // Attempts 3, 4+ should use the last delay (4)
    expect($getDelayMethod->invoke($policy, 3))->toBe(4);
    expect($getDelayMethod->invoke($policy, 4))->toBe(4);
    expect($getDelayMethod->invoke($policy, 10))->toBe(4);
});

test('respects max attempts configuration', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;
        throw new Exception('Always fails');
    };

    // Test with different max attempts
    $policy1 = new RetryPolicy(maxAttempts: 1);
    try {
        $policy1->execute($closure);
    } catch (Exception) {
    }
    expect($attempts)->toBe(1);

    $attempts = 0;
    $policy2 = new RetryPolicy(maxAttempts: 5);
    try {
        $policy2->execute($closure);
    } catch (Exception) {
    }
    expect($attempts)->toBe(5);

    $attempts = 0;
    $policy3 = new RetryPolicy(maxAttempts: 10);
    try {
        $policy3->execute($closure);
    } catch (Exception) {
    }
    expect($attempts)->toBe(10);
});

test('throws the last exception on all retries exhausted', function () {
    $closure = function () {
        throw new RuntimeException('Final error message');
    };

    $policy = new RetryPolicy(maxAttempts: 3);

    expect(fn () => $policy->execute($closure))
        ->toThrow(RuntimeException::class, 'Final error message');
});

test('throws immediately if first attempt fails and max attempts is 1', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;
        throw new Exception('Immediate failure');
    };

    $policy = new RetryPolicy(maxAttempts: 1);

    expect(fn () => $policy->execute($closure))
        ->toThrow(Exception::class);

    expect($attempts)->toBe(1);
});

test('succeeds on first attempt without retrying', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;

        return 'immediate success';
    };

    $policy = new RetryPolicy(maxAttempts: 5);
    $result = $policy->execute($closure);

    expect($result)->toBe('immediate success');
    expect($attempts)->toBe(1);
});

test('exponential backoff factory method respects max attempts', function () {
    $attempts = 0;
    $closure = function () use (&$attempts) {
        $attempts++;
        throw new Exception('Failure');
    };

    $policy = RetryPolicy::exponentialBackoff(baseDelay: 1, maxAttempts: 4);

    try {
        $policy->execute($closure);
    } catch (Exception) {
    }

    expect($attempts)->toBe(4);
});

test('jitter calculation uses correct percentage', function () {
    $policy = new RetryPolicy(maxAttempts: 5, backoffDelays: [100], useJitter: true);

    $reflection = new ReflectionClass($policy);
    $getDelayMethod = $reflection->getMethod('getDelay');
    $getDelayMethod->setAccessible(true);

    // Base delay 100, jitter is 20% = ±20
    // Expected range: [80, 120]
    $delays = [];
    for ($i = 0; $i < 100; $i++) {
        $delays[] = $getDelayMethod->invoke($policy, 0);
    }

    $minDelay = min($delays);
    $maxDelay = max($delays);

    expect($minDelay)->toBeGreaterThanOrEqual(80);
    expect($maxDelay)->toBeLessThanOrEqual(120);
});

test('handles different exception types', function () {
    $closure = function () {
        throw new InvalidArgumentException('Invalid argument');
    };

    $policy = new RetryPolicy(maxAttempts: 2);

    expect(fn () => $policy->execute($closure))
        ->toThrow(InvalidArgumentException::class, 'Invalid argument');
});

test('default configuration has sensible values', function () {
    $policy = new RetryPolicy;

    $reflection = new ReflectionClass($policy);

    $maxAttemptsProperty = $reflection->getProperty('maxAttempts');
    $maxAttemptsProperty->setAccessible(true);
    expect($maxAttemptsProperty->getValue($policy))->toBe(3);

    $backoffDelaysProperty = $reflection->getProperty('backoffDelays');
    $backoffDelaysProperty->setAccessible(true);
    expect($backoffDelaysProperty->getValue($policy))->toBe([1, 2, 4]);

    $useJitterProperty = $reflection->getProperty('useJitter');
    $useJitterProperty->setAccessible(true);
    expect($useJitterProperty->getValue($policy))->toBeTrue();
});
