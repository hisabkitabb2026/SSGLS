<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e)) {
                \Log::error('Application exception', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace_id' => bin2hex(random_bytes(6)),
                    'user_id' => auth()->id(),
                    'company_id' => request()->header('company'),
                    'url' => request()->fullUrl(),
                ]);
            }
        });
    }

    public function render($request, Throwable $exception): Response
    {
        if (! $request->expectsJson()) {
            return parent::render($request, $exception);
        }

        $traceId = bin2hex(random_bytes(6));
        $debug = config('app.debug');
        $statusCode = match (true) {
            method_exists($exception, 'getStatusCode') => $exception->getStatusCode(),
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            default => 500,
        };

        $message = $debug ? $exception->getMessage() : match ($exception::class) {
            AuthenticationException::class => 'Unauthenticated',
            AuthorizationException::class => 'Unauthorized',
            ModelNotFoundException::class => 'Resource not found',
            default => 'An error occurred. Please try again.',
        };

        $errorType = match ($exception::class) {
            ValidationException::class => 'validation_error',
            AuthenticationException::class => 'authentication_error',
            AuthorizationException::class => 'authorization_error',
            ApiException::class => 'api_error',
            default => 'server_error',
        };

        $response = [
            'success' => false,
            'message' => $message,
            'error' => ['type' => $errorType, 'code' => $exception->getCode()],
            'trace_id' => $traceId,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($debug) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return response()->json($response, $statusCode);
    }
}
