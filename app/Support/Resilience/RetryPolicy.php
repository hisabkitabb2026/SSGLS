<?php

declare(strict_types=1);

namespace App\Support\Resilience;

use Closure;
use Exception;

class RetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private array $backoffDelays = [1, 2, 4],
        private bool $useJitter = true
    ) {}

    public function execute(Closure $request)
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                return $request();
            } catch (Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxAttempts) {
                    $delaySeconds = $this->getDelay($attempt - 1);
                    sleep($delaySeconds);
                }
            }
        }

        throw $lastException;
    }

    private function getDelay(int $attemptIndex): int
    {
        $baseDelay = $this->backoffDelays[$attemptIndex] ?? end($this->backoffDelays);

        if (! $this->useJitter) {
            return $baseDelay;
        }

        // Add jitter (±20%) to prevent thundering herd
        $jitterAmount = (int) ($baseDelay * 0.2);
        $jitterRandom = random_int(-$jitterAmount, $jitterAmount);

        return max(0, $baseDelay + $jitterRandom);
    }

    public static function exponentialBackoff(
        int $baseDelay = 1,
        int $maxAttempts = 5,
        bool $useJitter = true
    ): self {
        $delays = [];
        for ($i = 0; $i < $maxAttempts; $i++) {
            $delays[] = $baseDelay * (2 ** $i);
        }

        return new self($maxAttempts, $delays, $useJitter);
    }

    public static function linear(
        int $baseDelay = 1,
        int $maxAttempts = 3
    ): self {
        $delays = array_fill(0, $maxAttempts, $baseDelay);

        return new self($maxAttempts, $delays, false);
    }

    public static function immediate(int $maxAttempts = 3): self
    {
        $delays = array_fill(0, $maxAttempts, 0);

        return new self($maxAttempts, $delays, false);
    }
}
