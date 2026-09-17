<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message = 'API request failed',
        int $code = 500,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error' => 'api_error',
        ], $this->code);
    }
}
