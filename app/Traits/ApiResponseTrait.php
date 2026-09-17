<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Standardised API response helpers for controllers.
 *
 * This trait eliminates the inconsistent response formats that had crept in
 * across controllers:
 *
 *  - Some returned `response()->json(['success' => true])`
 *  - Some returned `response()->json(['success' => true, 'data' => ...])`
 *  - Some returned `new Resource($model)` (no success key)
 *  - Errors used `respondJson()` returning `{error, message}` with 422,
 *    while others returned `{success: false, message}` with 404
 *  - One controller returned `{success: 'string message'}` (boolean vs string)
 *
 * Standard format after this trait:
 *
 *  Success (single):   `new Resource($model)`  (Laravel default — { data: {...} })
 *  Success (list):     `Resource::collection($models)->additional(['meta' => [...]])`
 *  Success (action):   `{ success: true }`  (status 200)
 *  Success (created):  `{ success: true }`  (status 201)
 *  Error (validation): `{ success: false, message: "..." }`  (status 422)
 *  Error (not found):  `{ success: false, message: "..." }`  (status 404)
 *  Error (server):     `{ success: false, message: "..." }`  (status 500)
 *
 * Controllers that already use API Resources for model responses should
 * continue doing so — this trait is for action responses (delete, send,
 * changeStatus) and error responses.
 */
trait ApiResponseTrait
{
    /**
     * Return a standard success response for action endpoints
     * (delete, send, changeStatus, etc.).
     *
     * @param  string|null  $message  Optional success message.
     * @param  int  $status  HTTP status code (default 200).
     * @param  array  $extra  Additional data to include in the response.
     */
    protected function successResponse(
        ?string $message = null,
        int $status = 200,
        array $extra = [],
    ): JsonResponse {
        $response = array_merge(['success' => true], $extra);

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a standard created response for store endpoints.
     *
     * @param  array  $extra  Additional data to include in the response.
     */
    protected function createdResponse(array $extra = []): JsonResponse
    {
        return $this->successResponse(null, 201, $extra);
    }

    /**
     * Return a standard error response.
     *
     * @param  string  $message  Human-readable error message.
     * @param  int  $status  HTTP status code (default 422).
     * @param  string|null  $errorCode  Optional machine-readable error code.
     * @param  array  $extra  Additional data to include in the response.
     */
    protected function errorResponse(
        string $message,
        int $status = 422,
        ?string $errorCode = null,
        array $extra = [],
    ): JsonResponse {
        $response = array_merge([
            'success' => false,
            'message' => $message,
        ], $extra);

        if ($errorCode !== null) {
            $response['error'] = $errorCode;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a 404 Not Found error response.
     *
     * @param  string  $message  Human-readable error message.
     * @param  array  $extra  Additional data to include in the response.
     */
    protected function notFoundResponse(
        string $message = 'Resource not found',
        array $extra = [],
    ): JsonResponse {
        return $this->errorResponse($message, 404, null, $extra);
    }

    /**
     * Return a 403 Forbidden error response.
     *
     * @param  string  $message  Human-readable error message.
     * @param  array  $extra  Additional data to include in the response.
     */
    protected function forbiddenResponse(
        string $message = 'This action is unauthorized',
        array $extra = [],
    ): JsonResponse {
        return $this->errorResponse($message, 403, null, $extra);
    }
}
