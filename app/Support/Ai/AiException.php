<?php

namespace App\Support\Ai;

use InvoiceShelf\Modules\Ai\Exceptions\AiException as ModuleAiException;
use Throwable;

/**
 * Domain exception for AI driver failures.
 *
 * Extends the module contract's AiException so that drivers registered
 * with the module Registry can throw this app-local subclass.
 *
 * Carries a short `errorKey` alongside the human-readable message so the
 * frontend can look up a localized error string. Matches the shape of
 * ExchangeRateException for consistency with the existing driver pattern.
 */
class AiException extends ModuleAiException
{
    public function __construct(
        string $message,
        string $errorKey = 'server_error',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $errorKey, $code, $previous);
    }
}
