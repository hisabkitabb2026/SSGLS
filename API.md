# InvoiceShelf API Documentation

Complete reference for the InvoiceShelf REST API. The API is built on Laravel with RESTful design principles and comprehensive validation.

## Table of Contents

1. [Base Configuration](#base-configuration)
2. [Authentication](#authentication)
3. [Request/Response Format](#requestresponse-format)
4. [Error Handling](#error-handling)
5. [Rate Limiting](#rate-limiting)
6. [API Endpoints](#api-endpoints)
7. [Common Patterns](#common-patterns)
8. [Examples](#examples)

## Base Configuration

### Base URL
```
Production: https://your-domain.com/api/v1
Development: http://localhost/api/v1
```

### Supported Content Types
```
Content-Type: application/json
Accept: application/json
```

### Required Headers

Every authenticated request must include:

```http
Authorization: Bearer {token}
Company: {company-slug}
Content-Type: application/json
```

- **Authorization** - Sanctum bearer token (obtain via login endpoint)
- **Company** - Company slug for multi-tenancy routing
- **Content-Type** - Always `application/json`

## Authentication

### Login

Obtain API token for subsequent requests.

```http
POST /v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

Response: 200 OK
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "companies": [
        {
          "id": 1,
          "name": "Acme Corp",
          "slug": "acme-corp"
        }
      ]
    },
    "token": "1|abcdef123456789..."
  }
}
```

### Logout

Revoke the current token.

```http
POST /v1/auth/logout
Authorization: Bearer {token}

Response: 200 OK
{
  "success": true,
  "message": "Logged out successfully"
}
```

### Token Usage

Include token in all authenticated requests:

```http
GET /v1/invoices
Authorization: Bearer 1|abcdef123456789...
Company: acme-corp
```

### Password Reset

**Request reset link:**
```http
POST /v1/auth/password/email
Content-Type: application/json

{
  "email": "user@example.com"
}

Response: 200 OK
{
  "success": true,
  "message": "Reset link sent to email"
}
```

**Reset password:**
```http
POST /v1/auth/reset/password
Content-Type: application/json

{
  "email": "user@example.com",
  "token": "reset-token-from-email",
  "password": "newpassword",
  "password_confirmation": "newpassword"
}

Response: 200 OK
{
  "success": true,
  "message": "Password reset successfully"
}
```

## Request/Response Format

### Standard Response Envelope

```json
{
  "success": true,
  "data": {
    // resource data
  }
}
```

### Paginated Response

```json
{
  "success": true,
  "data": [
    // resource array
  ],
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 15,
    "to": 15,
    "total": 73,
    "path": "http://localhost/api/v1/invoices"
  }
}
```

### Query Parameters

Standard pagination and filtering:

```http
GET /v1/invoices?page=1&per_page=20&sort=-created_at
```

- **page** - Page number (default: 1)
- **per_page** - Items per page (default: 15, max: 100)
- **sort** - Field to sort by (prefix with `-` for descending)
- **filter** - Filter parameters (domain-specific)

### Request Body Example

```json
{
  "customer_id": 1,
  "invoice_date": "2024-08-01",
  "due_date": "2024-09-01",
  "items": [
    {
      "item_id": 1,
      "quantity": 2,
      "price": 100.00
    }
  ]
}
```

## Error Handling

### Error Response Format

```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error message for field"]
  },
  "error_code": "VALIDATION_ERROR"
}
```

### HTTP Status Codes

| Code | Meaning | Example |
|------|---------|---------|
| 200 | Success | Invoice retrieved |
| 201 | Created | Invoice created |
| 204 | No Content | Resource deleted |
| 400 | Bad Request | Validation error |
| 401 | Unauthorized | Missing/invalid token |
| 403 | Forbidden | Permission denied |
| 404 | Not Found | Resource not found |
| 422 | Validation Failed | Invalid input |
| 429 | Rate Limited | Too many requests |
| 500 | Server Error | Unexpected error |

### Common Error Codes

```
VALIDATION_ERROR      - Request validation failed
UNAUTHORIZED          - Authentication required
FORBIDDEN             - Permission denied
NOT_FOUND             - Resource not found
DUPLICATE_ENTRY       - Resource already exists
INVALID_STATE         - Operation invalid for current state
RATE_LIMIT_EXCEEDED   - Too many requests
INTERNAL_ERROR        - Server-side error
```

### Example Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email field must be a valid email address"],
    "invoice_number": ["The invoice number must be unique"]
  },
  "error_code": "VALIDATION_ERROR"
}
```

## Rate Limiting

### Rate Limit Headers

Every response includes rate limit information:

```http
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 287
X-RateLimit-Reset: 1661234567
```

### Rate Limit Tiers

```
General endpoints:    300 requests per minute
Mutation endpoints:   100 requests per minute
AI endpoints:         20 requests per minute
Password reset:       10 requests per 2 minutes
```

### Rate Limit Response

When limit exceeded (HTTP 429):

```json
{
  "success": false,
  "message": "Rate limit exceeded",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

## API Endpoints

### Authentication

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/auth/login` | User login |
| POST | `/auth/logout` | User logout |
| POST | `/auth/password/email` | Request password reset |
| POST | `/auth/reset/password` | Reset password |
| GET | `/auth/check` | Check auth status |

### Invoices

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/invoices` | List invoices (paginated) |
| POST | `/invoices` | Create invoice |
| GET | `/invoices/{invoice}` | Get invoice details |
| PUT | `/invoices/{invoice}` | Update invoice |
| DELETE | `/invoices/{invoice}` | Delete invoice |
| POST | `/invoices/{invoice}/send` | Send invoice to customer |
| POST | `/invoices/{invoice}/send/preview` | Preview sent email |
| POST | `/invoices/{invoice}/status` | Change invoice status |
| POST | `/invoices/{invoice}/clone` | Clone/duplicate invoice |
| POST | `/invoices/{invoice}/convert-to-estimate` | Convert to estimate |
| POST | `/invoices/delete` | Bulk delete invoices |
| GET | `/invoices/next-number` | Get next invoice number |
| GET | `/invoices/templates` | List invoice templates |

### Customers

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/customers` | List customers (paginated) |
| POST | `/customers` | Create customer |
| GET | `/customers/{customer}` | Get customer details |
| PUT | `/customers/{customer}` | Update customer |
| DELETE | `/customers/{customer}` | Delete customer |
| GET | `/customers/{customer}/stats` | Customer statistics |
| POST | `/customers/delete` | Bulk delete customers |

### Items/Products

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/items` | List items (paginated) |
| POST | `/items` | Create item |
| GET | `/items/{item}` | Get item details |
| PUT | `/items/{item}` | Update item |
| DELETE | `/items/{item}` | Delete item |
| POST | `/items/delete` | Bulk delete items |
| GET | `/units` | List measurement units |
| POST | `/units` | Create unit |

### Expenses

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/expenses` | List expenses (paginated) |
| POST | `/expenses` | Create expense |
| GET | `/expenses/{expense}` | Get expense details |
| PUT | `/expenses/{expense}` | Update expense |
| DELETE | `/expenses/{expense}` | Delete expense |
| POST | `/expenses/{expense}/upload/receipts` | Upload receipt |
| GET | `/expenses/{expense}/show/receipt` | Get receipt |
| POST | `/expenses/delete` | Bulk delete expenses |
| GET | `/categories` | List categories |
| POST | `/categories` | Create category |
| PUT | `/categories/{category}` | Update category |
| DELETE | `/categories/{category}` | Delete category |

### Payments

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/payments` | List payments (paginated) |
| POST | `/payments` | Record payment |
| GET | `/payments/{payment}` | Get payment details |
| PUT | `/payments/{payment}` | Update payment |
| DELETE | `/payments/{payment}` | Delete payment |
| POST | `/payments/{payment}/send` | Send payment receipt |
| POST | `/payments/delete` | Bulk delete payments |

### Recurring Invoices

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/recurring-invoices` | List recurring invoices |
| POST | `/recurring-invoices` | Create recurring invoice |
| GET | `/recurring-invoices/{invoice}` | Get recurring invoice |
| PUT | `/recurring-invoices/{invoice}` | Update recurring invoice |
| DELETE | `/recurring-invoices/{invoice}` | Delete recurring invoice |
| POST | `/recurring-invoices/delete` | Bulk delete |
| GET | `/recurring-invoice-frequency` | List frequency options |

### Estimates

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/estimates` | List estimates (paginated) |
| POST | `/estimates` | Create estimate |
| GET | `/estimates/{estimate}` | Get estimate details |
| PUT | `/estimates/{estimate}` | Update estimate |
| DELETE | `/estimates/{estimate}` | Delete estimate |
| POST | `/estimates/{estimate}/send` | Send estimate |
| POST | `/estimates/{estimate}/status` | Change status |
| POST | `/estimates/{estimate}/convert-to-invoice` | Convert to invoice |
| POST | `/estimates/delete` | Bulk delete |
| GET | `/estimates/templates` | List templates |

### Transport Module

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/lorry-receipts` | List LR receipts |
| POST | `/lorry-receipts` | Create receipt |
| POST | `/lorry-receipts/delete` | Bulk delete |
| GET | `/lorry-party-profiles` | List party profiles |
| POST | `/lorry-party-profiles` | Create profile |
| GET | `/warehouse-items` | List warehouse items |
| POST | `/warehouse-items` | Create warehouse item |
| GET | `/warehouse-items/dashboard` | Warehouse dashboard |
| PATCH | `/warehouse-items/{id}/status` | Update status |
| GET | `/consolidation-groups` | List consolidations |
| POST | `/consolidation-groups` | Create consolidation |
| GET | `/load-trips` | List load trips |
| POST | `/load-trips` | Create load trip |
| PATCH | `/load-trips/{id}/dispatch` | Dispatch trip |

### Settings

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/me` | Get current user |
| PUT | `/me` | Update current user |
| GET | `/me/settings` | Get user settings |
| PUT | `/me/settings` | Update user settings |
| POST | `/me/upload-avatar` | Upload avatar |
| PUT | `/company` | Update company |
| POST | `/company/upload-logo` | Upload company logo |
| GET | `/company/settings` | Get company settings |
| POST | `/company/settings` | Update company settings |
| GET | `/settings` | Get global settings |
| POST | `/settings` | Update global settings |

### Configuration

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/bootstrap` | Get app bootstrap data |
| GET | `/config` | Get client config |
| GET | `/currencies` | List currencies |
| GET | `/countries` | List countries |
| GET | `/timezones` | List timezones |
| GET | `/date/formats` | Date format options |

## Common Patterns

### Creating a Resource

```http
POST /v1/invoices
Authorization: Bearer {token}
Company: {slug}
Content-Type: application/json

{
  "customer_id": 1,
  "invoice_date": "2024-08-01",
  "due_date": "2024-09-01",
  "items": [
    {
      "item_id": 1,
      "quantity": 2,
      "price": 100.00
    }
  ]
}

Response: 201 Created
{
  "success": true,
  "data": {
    "id": 123,
    "invoice_number": "INV-2024-001",
    "status": "draft",
    "total": 200.00,
    "customer": { ... }
  }
}
```

### Updating a Resource

```http
PUT /v1/invoices/123
Authorization: Bearer {token}
Company: {slug}
Content-Type: application/json

{
  "due_date": "2024-10-01",
  "notes": "Updated terms"
}

Response: 200 OK
{
  "success": true,
  "data": { ... }
}
```

### Deleting a Resource

```http
DELETE /v1/invoices/123
Authorization: Bearer {token}
Company: {slug}

Response: 204 No Content
```

### Bulk Operations

```http
POST /v1/invoices/delete
Authorization: Bearer {token}
Company: {slug}
Content-Type: application/json

{
  "ids": [1, 2, 3, 4, 5]
}

Response: 200 OK
{
  "success": true,
  "message": "5 invoices deleted successfully"
}
```

### Pagination

```http
GET /v1/invoices?page=2&per_page=20
Authorization: Bearer {token}
Company: {slug}

Response: 200 OK
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 2,
    "last_page": 5,
    "total": 73,
    "per_page": 20
  }
}
```

### Filtering

```http
GET /v1/invoices?status=published&customer_id=1
Authorization: Bearer {token}
Company: {slug}

Response: 200 OK
```

### Sorting

```http
GET /v1/invoices?sort=-created_at
Authorization: Bearer {token}
Company: {slug}

Response: 200 OK
```

## Examples

### Complete Invoice Creation Workflow

```bash
# 1. Authenticate
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'

# Response includes token
# TOKEN=1|abc123...

# 2. Get customers
curl -X GET "http://localhost/api/v1/customers?per_page=5" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Company: my-company"

# 3. Get items
curl -X GET "http://localhost/api/v1/items?per_page=10" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Company: my-company"

# 4. Create invoice
curl -X POST http://localhost/api/v1/invoices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Company: my-company" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "invoice_date": "2024-08-01",
    "due_date": "2024-09-01",
    "items": [
      {
        "item_id": 1,
        "quantity": 2,
        "price": 100.00
      },
      {
        "item_id": 2,
        "quantity": 1,
        "price": 50.00
      }
    ]
  }'

# 5. Send invoice to customer
curl -X POST http://localhost/api/v1/invoices/123/send \
  -H "Authorization: Bearer $TOKEN" \
  -H "Company: my-company" \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Please find attached invoice..."
  }'

# 6. Record payment
curl -X POST http://localhost/api/v1/payments \
  -H "Authorization: Bearer $TOKEN" \
  -H "Company: my-company" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_id": 123,
    "amount": 250.00,
    "payment_date": "2024-08-15",
    "payment_method": "bank_transfer"
  }'
```

### PHP/Laravel Example

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->withHeaders(['Company' => 'my-company'])
    ->post('https://localhost/api/v1/invoices', [
        'customer_id' => 1,
        'invoice_date' => '2024-08-01',
        'due_date' => '2024-09-01',
        'items' => [
            [
                'item_id' => 1,
                'quantity' => 2,
                'price' => 100.00
            ]
        ]
    ]);

$invoice = $response->json('data');
```

### JavaScript/Axios Example

```javascript
const api = axios.create({
  baseURL: 'https://localhost/api/v1',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Company': 'my-company'
  }
});

const invoice = await api.post('/invoices', {
  customer_id: 1,
  invoice_date: '2024-08-01',
  due_date: '2024-09-01',
  items: [
    {
      item_id: 1,
      quantity: 2,
      price: 100.00
    }
  ]
});
```

## Best Practices

1. **Always include Company header** - Required for multi-tenancy routing
2. **Handle rate limits gracefully** - Respect X-RateLimit-Reset header
3. **Use pagination** - Never request all items in one call
4. **Validate responses** - Check `success` flag and error structure
5. **Cache appropriately** - Cache configuration and reference data
6. **Use transactions** - For multi-step operations, use database transactions
7. **Implement retry logic** - Handle transient 5xx errors with exponential backoff
8. **Monitor token expiry** - Refresh tokens before expiration
