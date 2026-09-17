<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;

class ApiResponse
{
    /**
     * Return a success response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'trace_id' => request()->header('X-Request-ID') ?? (string) str()->uuid(),
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    /**
     * Return an error response with trace ID.
     */
    public static function error(
        string $message,
        string $type = 'general_error',
        int $code = 400,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'type' => $type,
            'trace_id' => request()->header('X-Request-ID') ?? (string) str()->uuid(),
            'errors' => $errors,
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    /**
     * Return a validation error response.
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed',
        int $code = 422
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'trace_id' => request()->header('X-Request-ID') ?? (string) str()->uuid(),
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    /**
     * Return a paginated response.
     */
    public static function paginated(
        mixed $items,
        Paginator $paginator
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'trace_id' => request()->header('X-Request-ID') ?? (string) str()->uuid(),
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }
}
