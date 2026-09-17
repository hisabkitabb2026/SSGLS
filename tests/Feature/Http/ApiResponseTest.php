<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Pagination\Paginator;

/**
 * Helper function to get decoded JSON from response
 */
function getJsonFromResponse($response)
{
    return json_decode($response->getContent(), true);
}

/**
 * Helper function to create a mock paginator
 */
function createMockPaginator(
    int $currentPage = 1,
    int $perPage = 2,
    int $total = 10,
    int $lastPage = 5,
    ?int $from = null,
    ?int $to = null,
    bool $hasMore = true
) {
    $from ??= ($currentPage - 1) * $perPage + 1;
    $to ??= min($currentPage * $perPage, $total);

    $paginator = Mockery::mock(Paginator::class);
    $paginator->shouldReceive('currentPage')->andReturn($currentPage);
    $paginator->shouldReceive('perPage')->andReturn($perPage);
    $paginator->shouldReceive('total')->andReturn($total);
    $paginator->shouldReceive('lastPage')->andReturn($lastPage);
    $paginator->shouldReceive('firstItem')->andReturn($from);
    $paginator->shouldReceive('lastItem')->andReturn($to);
    $paginator->shouldReceive('hasMorePages')->andReturn($hasMore);

    return $paginator;
}

// =====================================================================
// Success Response Tests
// =====================================================================

test('success() returns 200 status code', function () {
    $response = ApiResponse::success(['id' => 1, 'name' => 'Test']);

    expect($response->getStatusCode())->toBe(200);
});

test('success() returns data in response', function () {
    $data = ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'];
    $response = ApiResponse::success($data);
    $json = getJsonFromResponse($response);

    expect($json['data'])->toBe($data);
});

test('success() includes success flag set to true', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json['success'])->toBeTrue();
});

test('success() includes default message', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json['message'])->toBe('Success');
});

test('success() includes custom message', function () {
    $response = ApiResponse::success(null, 'User created successfully');
    $json = getJsonFromResponse($response);

    expect($json['message'])->toBe('User created successfully');
});

test('success() includes trace_id', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json['trace_id'])->toBeTruthy();
    expect($json['trace_id'])->toBeString();
});

test('success() includes timestamp', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json['timestamp'])->toBeTruthy();
    expect($json['timestamp'])->toBeString();
});

test('success() with custom status code', function () {
    $response = ApiResponse::success(['created' => true], 'Created', 201);
    $json = getJsonFromResponse($response);

    expect($response->getStatusCode())->toBe(201);
    expect($json['data']['created'])->toBeTrue();
});

test('success() with null data', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json['data'])->toBeNull();
});

// =====================================================================
// Error Response Tests
// =====================================================================

test('error() returns proper status code', function () {
    $response = ApiResponse::error('Resource not found', 'not_found', 404);

    expect($response->getStatusCode())->toBe(404);
});

test('error() includes error type', function () {
    $response = ApiResponse::error('Something went wrong', 'general_error');
    $json = getJsonFromResponse($response);

    expect($json['type'])->toBe('general_error');
});

test('error() returns success flag as false', function () {
    $response = ApiResponse::error('An error occurred');
    $json = getJsonFromResponse($response);

    expect($json['success'])->toBeFalse();
});

test('error() includes error message', function () {
    $response = ApiResponse::error('Database connection failed');
    $json = getJsonFromResponse($response);

    expect($json['message'])->toBe('Database connection failed');
});

test('error() includes trace_id', function () {
    $response = ApiResponse::error('Error occurred');
    $json = getJsonFromResponse($response);

    expect($json['trace_id'])->toBeTruthy();
    expect($json['trace_id'])->toBeString();
});

test('error() includes timestamp', function () {
    $response = ApiResponse::error('Error occurred');
    $json = getJsonFromResponse($response);

    expect($json['timestamp'])->toBeTruthy();
    expect($json['timestamp'])->toBeString();
});

test('error() includes errors array', function () {
    $errors = ['field' => 'Email is required'];
    $response = ApiResponse::error('Validation failed', 'validation_error', 422, $errors);
    $json = getJsonFromResponse($response);

    expect($json['errors'])->toBe($errors);
});

test('error() with default status code is 400', function () {
    $response = ApiResponse::error('Bad request');

    expect($response->getStatusCode())->toBe(400);
});

test('error() with custom error type', function () {
    $response = ApiResponse::error('Unauthorized access', 'unauthorized_access', 403);
    $json = getJsonFromResponse($response);

    expect($json['type'])->toBe('unauthorized_access');
});

test('error() generates unique trace_id', function () {
    $response1 = ApiResponse::error('Error 1');
    $response2 = ApiResponse::error('Error 2');
    $json1 = getJsonFromResponse($response1);
    $json2 = getJsonFromResponse($response2);

    expect($json1['trace_id'])->not->toBe($json2['trace_id']);
});

// =====================================================================
// Validation Error Response Tests
// =====================================================================

test('validationError() returns 422 status code', function () {
    $response = ApiResponse::validationError(['email' => 'Email is required']);

    expect($response->getStatusCode())->toBe(422);
});

test('validationError() returns errors array', function () {
    $errors = [
        'email' => 'Email is required',
        'password' => 'Password must be at least 8 characters',
    ];
    $response = ApiResponse::validationError($errors);
    $json = getJsonFromResponse($response);

    expect($json['errors'])->toBe($errors);
});

test('validationError() includes success flag as false', function () {
    $response = ApiResponse::validationError(['field' => 'Error']);
    $json = getJsonFromResponse($response);

    expect($json['success'])->toBeFalse();
});

test('validationError() includes default message', function () {
    $response = ApiResponse::validationError(['field' => 'Error']);
    $json = getJsonFromResponse($response);

    expect($json['message'])->toBe('Validation failed');
});

test('validationError() includes custom message', function () {
    $response = ApiResponse::validationError(['field' => 'Error'], 'Custom validation message');
    $json = getJsonFromResponse($response);

    expect($json['message'])->toBe('Custom validation message');
});

test('validationError() includes trace_id', function () {
    $response = ApiResponse::validationError(['field' => 'Error']);
    $json = getJsonFromResponse($response);

    expect($json['trace_id'])->toBeTruthy();
    expect($json['trace_id'])->toBeString();
});

test('validationError() includes timestamp', function () {
    $response = ApiResponse::validationError(['field' => 'Error']);
    $json = getJsonFromResponse($response);

    expect($json['timestamp'])->toBeTruthy();
    expect($json['timestamp'])->toBeString();
});

test('validationError() with custom status code', function () {
    $response = ApiResponse::validationError(['field' => 'Error'], 'Invalid input', 400);

    expect($response->getStatusCode())->toBe(400);
});

test('validationError() with nested error messages', function () {
    $errors = [
        'user' => [
            'email' => 'Email is invalid',
            'phone' => 'Phone is required',
        ],
    ];
    $response = ApiResponse::validationError($errors);
    $json = getJsonFromResponse($response);

    expect($json['errors']['user']['email'])->toBe('Email is invalid');
    expect($json['errors']['user']['phone'])->toBe('Phone is required');
});

test('validationError() with array of errors per field', function () {
    $errors = [
        'email' => ['Email is required', 'Email must be unique'],
    ];
    $response = ApiResponse::validationError($errors);
    $json = getJsonFromResponse($response);

    expect($json['errors']['email'])->toBe($errors['email']);
});

// =====================================================================
// Paginated Response Tests
// =====================================================================

test('paginated() returns 200 status code', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(1, 2, 2, 1, 1, 2, false);

    $response = ApiResponse::paginated($items, $paginator);

    expect($response->getStatusCode())->toBe(200);
});

test('paginated() includes success flag as true', function () {
    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['success'])->toBeTrue();
});

test('paginated() includes data array', function () {
    $items = [['id' => 1, 'name' => 'Item 1'], ['id' => 2, 'name' => 'Item 2']];
    $paginator = createMockPaginator(1, 2, 2, 1, 1, 2, false);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['data'])->toBe($items);
});

test('paginated() includes pagination metadata', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator();

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination'])->toBeArray();
    expect($json['pagination'])->toHaveKeys([
        'current_page',
        'per_page',
        'total',
        'last_page',
        'from',
        'to',
        'has_more',
    ]);
});

test('paginated() includes correct current page', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(2, 2, 10, 5, 3, 4, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['current_page'])->toBe(2);
});

test('paginated() includes correct per page', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(1, 2, 10, 5, 1, 2, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['per_page'])->toBe(2);
});

test('paginated() includes correct total count', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(1, 2, 10, 5, 1, 2, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['total'])->toBe(10);
});

test('paginated() includes correct last page', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(1, 2, 10, 5, 1, 2, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['last_page'])->toBe(5);
});

test('paginated() includes from and to pagination bounds', function () {
    $items = [['id' => 3], ['id' => 4]];
    $paginator = createMockPaginator(2, 2, 10, 5, 3, 4, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['from'])->toBe(3);
    expect($json['pagination']['to'])->toBe(4);
});

test('paginated() includes has_more flag', function () {
    $items = [['id' => 1], ['id' => 2]];
    $paginator = createMockPaginator(1, 2, 10, 5, 1, 2, true);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['has_more'])->toBeTrue();
});

test('paginated() has_more is false on last page', function () {
    $items = [['id' => 9], ['id' => 10]];
    $paginator = createMockPaginator(5, 2, 10, 5, 9, 10, false);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['pagination']['has_more'])->toBeFalse();
});

test('paginated() includes trace_id', function () {
    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['trace_id'])->toBeTruthy();
    expect($json['trace_id'])->toBeString();
});

test('paginated() includes timestamp', function () {
    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);

    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json['timestamp'])->toBeTruthy();
    expect($json['timestamp'])->toBeString();
});

// =====================================================================
// Trace ID and Timestamp Cross-Response Tests
// =====================================================================

test('all response types include trace_id', function () {
    $successResponse = ApiResponse::success(['data' => 'test']);
    $errorResponse = ApiResponse::error('Error occurred');
    $validationResponse = ApiResponse::validationError(['field' => 'Error']);

    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);
    $paginatedResponse = ApiResponse::paginated($items, $paginator);

    $successJson = getJsonFromResponse($successResponse);
    $errorJson = getJsonFromResponse($errorResponse);
    $validationJson = getJsonFromResponse($validationResponse);
    $paginatedJson = getJsonFromResponse($paginatedResponse);

    expect($successJson['trace_id'])->toBeTruthy();
    expect($errorJson['trace_id'])->toBeTruthy();
    expect($validationJson['trace_id'])->toBeTruthy();
    expect($paginatedJson['trace_id'])->toBeTruthy();
});

test('all response types include timestamp', function () {
    $successResponse = ApiResponse::success(['data' => 'test']);
    $errorResponse = ApiResponse::error('Error occurred');
    $validationResponse = ApiResponse::validationError(['field' => 'Error']);

    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);
    $paginatedResponse = ApiResponse::paginated($items, $paginator);

    $successJson = getJsonFromResponse($successResponse);
    $errorJson = getJsonFromResponse($errorResponse);
    $validationJson = getJsonFromResponse($validationResponse);
    $paginatedJson = getJsonFromResponse($paginatedResponse);

    expect($successJson['timestamp'])->toBeTruthy();
    expect($errorJson['timestamp'])->toBeTruthy();
    expect($validationJson['timestamp'])->toBeTruthy();
    expect($paginatedJson['timestamp'])->toBeTruthy();
});

test('trace_id is valid UUID format or string', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);
    $traceId = $json['trace_id'];

    // Should be a non-empty string
    expect($traceId)->toBeString();
    expect(strlen($traceId))->toBeGreaterThan(0);
});

test('timestamp is ISO 8601 format', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);
    $timestamp = $json['timestamp'];

    // ISO 8601 validation - should contain T and Z or offset
    expect(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $timestamp))->toBe(1);
});

test('multiple responses have different trace_ids', function () {
    $response1 = ApiResponse::success(['id' => 1]);
    $response2 = ApiResponse::success(['id' => 2]);
    $response3 = ApiResponse::success(['id' => 3]);

    $json1 = getJsonFromResponse($response1);
    $json2 = getJsonFromResponse($response2);
    $json3 = getJsonFromResponse($response3);

    expect($json1['trace_id'])->not->toBe($json2['trace_id']);
    expect($json2['trace_id'])->not->toBe($json3['trace_id']);
});

// =====================================================================
// Integration and Structure Tests
// =====================================================================

test('response JSON structure is valid and parseable', function () {
    $response = ApiResponse::success(['key' => 'value']);

    expect($response->getContent())->toBeString();
    $decoded = json_decode($response->getContent(), true);
    expect($decoded)->toBeArray();
});

test('success response has required fields at top level', function () {
    $response = ApiResponse::success();
    $json = getJsonFromResponse($response);

    expect($json)->toHaveKeys(['success', 'data', 'message', 'trace_id', 'timestamp']);
});

test('error response has required fields', function () {
    $response = ApiResponse::error('Error message');
    $json = getJsonFromResponse($response);

    expect($json)->toHaveKeys(['success', 'message', 'type', 'trace_id', 'errors', 'timestamp']);
});

test('validation error response has required fields', function () {
    $response = ApiResponse::validationError(['field' => 'Error']);
    $json = getJsonFromResponse($response);

    expect($json)->toHaveKeys(['success', 'message', 'errors', 'trace_id', 'timestamp']);
});

test('paginated response has required fields', function () {
    $items = [['id' => 1]];
    $paginator = createMockPaginator(1, 1, 1, 1, 1, 1, false);
    $response = ApiResponse::paginated($items, $paginator);
    $json = getJsonFromResponse($response);

    expect($json)->toHaveKeys(['success', 'data', 'pagination', 'trace_id', 'timestamp']);
});
