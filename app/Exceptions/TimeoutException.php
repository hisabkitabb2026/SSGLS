<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TimeoutException extends Exception
{
    protected $code = 504;

    public function __construct(string $message = 'Request timeout')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return 504;
    }
}
