# Settings Service API Documentation

## Table of Contents
1. [Authentication](#authentication)
2. [Health Endpoints](#health-endpoints)
3. [Settings Endpoints](#settings-endpoints)
4. [Batch Operations](#batch-operations)
5. [Audit Logs](#audit-logs)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Examples](#examples)

## Authentication

All API endpoints (except `/health` and `/ready`) require JWT authentication.

### JWT Token Format

Include the token in the `Authorization` header:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Required Claims

```json
{
  "user_id": 1,
  "company_id": 1,
  "email": "user@example.com",
  "roles": ["admin"],
  "exp": 1234567890
}
```

**Note:** The JWT `company_id` claim determines which company's settings are accessible. Settings are automatically isolated by this claim.

## Health Endpoints

### Get Service Health

**Endpoint:** `GET /health`

**Authentication:** Not required

**Response (200 OK):**
```json
{
  "status": "healthy",
  "service": "settings-microservice",
  "version": "1.0.0",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Get Service Readiness

**Endpoint:** `GET /ready`

**Authentication:** Not required

**Description:** Checks if the service is ready to accept requests. Verifies database connectivity.

**Response (200 OK):**
```json
{
  "ready": true,
  "service": "settings-microservice",
  "database": true,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

**Response (503 Service Unavailable):**
```json
{
  "ready": false,
  "service": "settings-microservice",
  "database": false,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Settings Endpoints

### Create Setting

**Endpoint:** `POST /api/v1/settings`

**Authentication:** Required

**Request Body:**
```json
{
  "key": "app_name",
  "value": "My Application",
  "module": "general",
  "type": "string",
  "is_active": true,
  "created_by": 1
}
```

**Request Fields:**
- `key` (string, required): Unique setting key. Max 255 characters.
- `value` (any, required): Setting value (string, number, boolean, object, array)
- `module` (string, optional): Module/category. Max 100 characters.
- `type` (string, optional): Data type hint. Max 50 characters.
- `is_active` (boolean, optional): Whether setting is active. Default: true
- `created_by` (integer, required): User ID creating the setting

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "company_id": 1,
    "key": "app_name",
    "value": "My Application",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1,
    "updated_by": null,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "message": "Setting created successfully"
}
```

**Example cURL:**
```bash
curl -X POST http://localhost:8005/api/v1/settings \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "app_name",
    "value": "My App",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1
  }'
```

### Get Setting by ID

**Endpoint:** `GET /api/v1/settings/:id`

**Authentication:** Required

**URL Parameters:**
- `id` (string, required): Setting UUID

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "company_id": 1,
    "key": "app_name",
    "value": "My Application",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1,
    "updated_by": null,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  }
}
```

**Response (404 Not Found):**
```json
{
  "success": false,
  "error": "Setting not found",
  "status_code": 404,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Get Setting by Key

**Endpoint:** `GET /api/v1/settings/key/:key`

**Authentication:** Required

**URL Parameters:**
- `key` (string, required): Setting key

**Response:** Same as Get Setting by ID

### List Settings

**Endpoint:** `GET /api/v1/settings`

**Authentication:** Required

**Query Parameters:**
- `page` (integer, optional): Page number. Default: 1
- `page_size` (integer, optional): Results per page. Min: 1, Max: 100. Default: 20
- `module` (string, optional): Filter by module

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "company_id": 1,
      "key": "app_name",
      "value": "My Application",
      "module": "general",
      "type": "string",
      "is_active": true,
      "created_by": 1,
      "created_at": "2024-01-15T10:30:00Z",
      "updated_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 50,
    "page": 1,
    "page_size": 20,
    "total_pages": 3
  }
}
```

**Example cURL:**
```bash
curl -X GET "http://localhost:8005/api/v1/settings?page=1&page_size=20&module=general" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Update Setting

**Endpoint:** `PUT /api/v1/settings/:id`

**Authentication:** Required

**URL Parameters:**
- `id` (string, required): Setting UUID

**Request Body:**
```json
{
  "key": "app_name",
  "value": "Updated Application Name",
  "module": "general",
  "type": "string",
  "is_active": true,
  "updated_by": 1
}
```

**Request Fields:**
- `key` (string, optional): New key
- `value` (any, optional): New value
- `module` (string, optional): New module
- `type` (string, optional): New type
- `is_active` (boolean, optional): Active status
- `updated_by` (integer, required): User ID performing the update

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "company_id": 1,
    "key": "app_name",
    "value": "Updated Application Name",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1,
    "updated_by": 1,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:35:00Z"
  },
  "message": "Setting updated successfully"
}
```

### Update Setting by Key

**Endpoint:** `PUT /api/v1/settings/key/:key`

**Authentication:** Required

**URL Parameters:**
- `key` (string, required): Setting key

**Request Body:**
Same as Update Setting endpoint

**Response:** Same as Update Setting endpoint

### Delete Setting

**Endpoint:** `DELETE /api/v1/settings/:id`

**Authentication:** Required

**URL Parameters:**
- `id` (string, required): Setting UUID

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Setting deleted successfully"
}
```

**Note:** Delete is a soft delete. The record is marked as deleted but not removed from the database.

## Batch Operations

### Batch Create Settings

**Endpoint:** `POST /api/v1/settings/batch`

**Authentication:** Required

**Request Body:**
```json
{
  "settings": [
    {
      "key": "setting_key_1",
      "value": "value_1",
      "module": "module_1",
      "type": "string",
      "is_active": true,
      "created_by": 1
    },
    {
      "key": "setting_key_2",
      "value": 42,
      "module": "module_2",
      "type": "integer",
      "is_active": true,
      "created_by": 1
    }
  ]
}
```

**Request Constraints:**
- `settings` array: minimum 1, maximum unlimited
- Each setting must have required fields: `key`, `value`, `created_by`

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Settings created successfully",
  "count": 2
}
```

**Example cURL:**
```bash
curl -X POST http://localhost:8005/api/v1/settings/batch \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": [
      {
        "key": "key1",
        "value": "value1",
        "created_by": 1
      },
      {
        "key": "key2",
        "value": "value2",
        "created_by": 1
      }
    ]
  }'
```

### Batch Update Settings

**Endpoint:** `PUT /api/v1/settings/batch`

**Authentication:** Required

**Request Body:**
```json
{
  "settings": {
    "setting_key_1": "new_value_1",
    "setting_key_2": "new_value_2",
    "setting_key_3": {
      "nested": "object_value"
    }
  },
  "updated_by": 1
}
```

**Request Fields:**
- `settings` (object, required): Key-value pairs where key is the setting key and value is the new value
- `updated_by` (integer, required): User ID performing the update

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Settings updated successfully",
  "count": 2
}
```

**Note:** Only updates settings that exist. Non-existent keys are silently skipped.

## Audit Logs

### Get Audit Logs

**Endpoint:** `GET /api/v1/settings/audit-logs`

**Authentication:** Required

**Query Parameters:**
- `page` (integer, optional): Page number. Default: 1
- `page_size` (integer, optional): Results per page. Min: 1, Max: 100. Default: 20

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "log-550e8400-e29b-41d4-a716-446655440000",
      "company_id": 1,
      "setting_id": "550e8400-e29b-41d4-a716-446655440000",
      "action": "create",
      "old_value": null,
      "new_value": "My Application",
      "changed_by": 1,
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2024-01-15T10:30:00Z"
    },
    {
      "id": "log-550e8400-e29b-41d4-a716-446655440001",
      "company_id": 1,
      "setting_id": "550e8400-e29b-41d4-a716-446655440000",
      "action": "update",
      "old_value": "My Application",
      "new_value": "Updated Application",
      "changed_by": 1,
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2024-01-15T10:35:00Z"
    }
  ],
  "pagination": {
    "total": 100,
    "page": 1,
    "page_size": 20,
    "total_pages": 5
  }
}
```

**Audit Log Fields:**
- `action`: One of "create", "update", "delete"
- `old_value`: Previous value (null for create)
- `new_value`: New value (null for delete)
- `changed_by`: User ID who made the change
- `ip_address`: IP address of the requester
- `user_agent`: User agent of the request

## Error Handling

### Error Response Format

```json
{
  "success": false,
  "error": "Error message",
  "status_code": 400,
  "timestamp": "2024-01-15T10:30:00Z",
  "details": "Additional error details"
}
```

### Common Error Codes

| Code | Error | Description |
|------|-------|-------------|
| 400 | Bad Request | Invalid request parameters or body |
| 401 | Unauthorized | Missing or invalid JWT token |
| 403 | Forbidden | User lacks permission (company isolation) |
| 404 | Not Found | Setting not found |
| 409 | Conflict | Duplicate key for the company |
| 500 | Internal Server Error | Server error |
| 503 | Service Unavailable | Service not ready (database down, etc.) |

### Example Errors

**Invalid Request Body:**
```json
{
  "success": false,
  "error": "Invalid request body",
  "status_code": 400,
  "timestamp": "2024-01-15T10:30:00Z",
  "details": "key is required"
}
```

**Unauthorized:**
```json
{
  "success": false,
  "error": "Missing authorization header",
  "status_code": 401,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

**Not Found:**
```json
{
  "success": false,
  "error": "Setting not found",
  "status_code": 404,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Rate Limiting

Currently, there is no built-in rate limiting. Implement at the API Gateway level (Kong, AWS API Gateway, etc.) or add middleware as needed.

## Examples

### Example 1: Create and Update a Setting

```bash
#!/bin/bash

TOKEN="your-jwt-token"
BASE_URL="http://localhost:8005/api/v1"

# Create a setting
CREATE_RESPONSE=$(curl -s -X POST $BASE_URL/settings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "app_timeout",
    "value": 30,
    "module": "performance",
    "type": "integer",
    "is_active": true,
    "created_by": 1
  }')

echo "Create Response:"
echo $CREATE_RESPONSE | jq '.'

# Extract the setting ID
SETTING_ID=$(echo $CREATE_RESPONSE | jq -r '.data.id')

# Update the setting
curl -s -X PUT $BASE_URL/settings/$SETTING_ID \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "value": 60,
    "updated_by": 1
  }' | jq '.'
```

### Example 2: List Settings by Module

```bash
curl -X GET "http://localhost:8005/api/v1/settings?module=billing&page=1&page_size=10" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" | jq '.'
```

### Example 3: Batch Operations

```bash
#!/bin/bash

TOKEN="your-jwt-token"
BASE_URL="http://localhost:8005/api/v1"

# Batch create
curl -X POST $BASE_URL/settings/batch \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": [
      {"key": "k1", "value": "v1", "created_by": 1},
      {"key": "k2", "value": "v2", "created_by": 1},
      {"key": "k3", "value": 100, "created_by": 1}
    ]
  }' | jq '.'

# Batch update
curl -X PUT $BASE_URL/settings/batch \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "k1": "updated_v1",
      "k2": "updated_v2",
      "k3": 200
    },
    "updated_by": 1
  }' | jq '.'
```

### Example 4: Get Audit Trail

```bash
curl -X GET "http://localhost:8005/api/v1/settings/audit-logs?page=1&page_size=50" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" | jq '.data[] | {action, old_value, new_value, created_at}'
```

## Metrics Endpoint

**Endpoint:** `GET /metrics`

**Authentication:** Not required

**Description:** Returns Prometheus metrics for monitoring

**Response:** Prometheus text format
```
# HELP http_requests_total Total HTTP requests
# TYPE http_requests_total counter
http_requests_total{endpoint="/api/v1/settings",method="POST"} 42

# HELP http_request_duration_seconds HTTP request latency
# TYPE http_request_duration_seconds histogram
http_request_duration_seconds_bucket{endpoint="/api/v1/settings",le="0.1"} 38
```

## Best Practices

1. **Use Keys as Identifiers**: When you have the setting key, prefer using `/api/v1/settings/key/:key` for direct access
2. **Batch Operations**: Use batch endpoints for multiple operations to reduce API calls
3. **Pagination**: Always paginate when listing to manage memory and response times
4. **Error Handling**: Always check the `success` field in responses
5. **Audit Logging**: Review audit logs regularly for compliance and debugging
6. **JWT Expiry**: Implement token refresh logic in clients
7. **Company Isolation**: Rely on JWT `company_id` claim for multi-tenant isolation
