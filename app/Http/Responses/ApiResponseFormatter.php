<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;

/**

Standardizes all API responses to a consistent format
*/
class ApiResponseFormatter +{
/**
* Success response with data
*/
public static function success(
  mixed $data = null,
  string $message = 'Success',
  int $status = 200
): JsonResponse {
  return response()->json([
      'success' => true,
      'message' => $message,
      'data' => $data,
  ], $status);
}

/**
* Paginated response
*/
public static function paginated(
  Paginator $paginator,
  string $message = 'Success',
  int $status = 200
): JsonResponse {
  return response()->json([
      'success' => true,
      'message' => $message,
      'data' => $paginator->items(),
      'meta' => [
          'total' => $paginator->total(),
          'per_page' => $paginator->perPage(),
          'current_page' => $paginator->currentPage(),
          'last_page' => $paginator->lastPage(),
          'from' => $paginator->firstItem(),
          'to' => $paginator->lastItem(),
      ],
  ], $status);
}

/**
* Error response
*/
public static function error(
  string $message = 'An error occurred',
  array $errors = [],
  int $status = 400
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
      'errors' => $errors,
  ], $status);
}

/**
* Validation error response
*/
public static function validationError(
  array $errors,
  string $message = 'Validation failed',
  int $status = 422
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
      'errors' => $errors,
  ], $status);
}

/**
* Not found response
*/
public static function notFound(
  string $message = 'Resource not found',
  int $status = 404
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
  ], $status);
}

/**
* Unauthorized response
*/
public static function unauthorized(
  string $message = 'Unauthorized',
  int $status = 401
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
  ], $status);
}

/**
* Forbidden response
*/
public static function forbidden(
  string $message = 'You do not have permission to perform this action',
  int $status = 403
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
  ], $status);
}

/**
* Server error response
*/
public static function serverError(
  string $message = 'An internal server error occurred',
  int $status = 500
): JsonResponse {
  return response()->json([
      'success' => false,
      'message' => $message,
  ], $status);
}
}