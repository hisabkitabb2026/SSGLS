<?php

namespace App\Services\EventBus\Retry;

use Illuminate\Support\Facades\Log;

/**
 * Retry Policy with Exponential Backoff
 *
 * Implements retry logic with exponential backoff and jitter for event handling
 */
class RetryPolicy
{
    protected array $config;

    protected int $attempt = 1;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('event-bus.retry', []);
    }

    /**
     * Execute with retry policy
     */
    public function execute(callable $callback, string $context = ''): mixed
    {
        $maxAttempts = $this->config['max_attempts'] ?? 5;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback($attempt);
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $maxAttempts) {
                    $delay = $this->calculateDelay($attempt);
                    Log::warning('Retrying after delay', [
                        'context' => $context,
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000); // Convert milliseconds to microseconds
                } else {
                    Log::error('Max retries exceeded', [
                        'context' => $context,
                        'max_attempts' => $maxAttempts,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Calculate delay with exponential backoff and jitter
     */
    protected function calculateDelay(int $attempt): int
    {
        $initialDelay = $this->config['initial_delay'] ?? 1000;
        $maxDelay = $this->config['max_delay'] ?? 300000;
        $multiplier = $this->config['multiplier'] ?? 2;
        $useJitter = $this->config['jitter'] ?? true;

        // Exponential backoff: initialDelay * multiplier^(attempt-1)
        $delay = intval($initialDelay * pow($multiplier, $attempt - 1));

        // Cap the delay
        $delay = min($delay, $maxDelay);

        // Add jitter if enabled
        if ($useJitter) {
            $jitter = mt_rand(0, intval($delay * 0.1)); // 10% jitter
            $delay += $jitter;
        }

        return $delay;
    }

    /**
     * Get delay for specific attempt
     */
    public function getDelay(int $attempt): int
    {
        return $this->calculateDelay($attempt);
    }

    /**
     * Check if should retry
     */
    public function shouldRetry(int $attempt, \Exception $exception): bool
    {
        $maxAttempts = $this->config['max_attempts'] ?? 5;

        return $attempt < $maxAttempts;
    }

    /**
     * Get retry count configuration
     */
    public function getMaxAttempts(): int
    {
        return $this->config['max_attempts'] ?? 5;
    }

    /**
     * Get retry configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
