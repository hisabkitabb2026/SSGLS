<?php

declare(strict_types=1);

namespace App\Support\Resilience;

use App\Exceptions\TimeoutException;
use Closure;

class Timeout
{
    public static function execute(Closure $callback, int $seconds = 30)
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_alarm')) {
            return $callback();
        }

        $oldHandler = pcntl_signal_get_handler(SIGALRM);
        pcntl_signal(SIGALRM, function () {
            throw new TimeoutException('Operation timed out');
        });

        pcntl_alarm($seconds);

        try {
            $result = $callback();
            pcntl_alarm(0);

            return $result;
        } catch (TimeoutException $e) {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $oldHandler);

            throw $e;
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $oldHandler);
        }
    }

    public static function isSupported(): bool
    {
        return function_exists('pcntl_signal') && function_exists('pcntl_alarm');
    }
}
