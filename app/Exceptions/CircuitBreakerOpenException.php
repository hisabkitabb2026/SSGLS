<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CircuitBreakerOpenException extends Exception
{
    protected $code = 503;

    public function __construct(string $message = 'Service temporarily unavailable')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 503;
    }
}
