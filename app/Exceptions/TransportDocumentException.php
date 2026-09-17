<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TransportDocumentException extends Exception
{
    public function __construct(
        string $message = 'Transport document operation failed',
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->message,
            'error' => 'transport_document_error',
        ], $this->code);
    }
}
