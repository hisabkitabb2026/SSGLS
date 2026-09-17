# Customer Service OpenAPI Specification

## Base URL
```
http://localhost:8004/api/v1
```

## Authentication
All endpoints (except health checks) require:
- `Authorization: Bearer <JWT_TOKEN>` header
- `X-Company-ID: <COMPANY_ID>` header

## Endpoints

### Health & Monitoring

#### Health Check
```
GET /health
```

Response:
```json
{
  "status": "healthy",
  "service": "customer-service",
  "timestamp": "2024-01-01T00:00:00Z"
}
```

#### Readiness Probe
```
GET /ready
```

Response:
```json
{
  "status": "ready",
  "service": "customer-service"
}
```

#### Metrics
```
GET /metrics
```

Response:
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-01-01T00:00:00Z",
    "uptime": 3600,
    "memory": {...},
    "http": {...},
    "database": {...},
    "events": {...}
  }
}
```

---

## Customer Endpoints

### Create Customer
```
POST /customers
Authorization: Bearer <JWT>
X-Company-ID: <ID>
Content-Type: application/json
```

Request Body:
```json
{
  "email": "customer@example.com",
  "name": "John Doe",
  "phone": "123-456-7890",
  "address": "123 Main St",
  "city": "New York",
  "state": "NY",
  "postalCode": "10001",
  "country": "USA",
  "taxId": "123456789",
  "currencyCode": "USD",
  "website": "https://example.com",
  "notes": "Important customer"
}
```

Response (201):
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "companyId": 1,
    "email": "customer@example.com",
    "name": "John Doe",
    "phone": "123-456-7890",
    "address": "123 Main St",
    "city": "New York",
    "state": "NY",
    "postalCode": "10001",
    "country": "USA",
    "taxId": "123456789",
    "currencyCode": "USD",
    "website": "https://example.com",
    "notes": "Important customer",
    "status": "active",
    "createdAt": "2024-01-01T00:00:00Z",
    "updatedAt": "2024-01-01T00:00:00Z",
    "createdBy": 123
  }
}
```

Error (409):
```json
{
  "error": "Customer with email customer@example.com already exists",
  "statusCode": 409
}
```

---

### List Customers
```
GET /customers?status=active&limit=50&offset=0
Authorization: Bearer <JWT>
X-Company-ID: <ID>
```

Query Parameters:
- `status` (optional): Filter by status (active|inactive|archived)
- `limit` (optional): Results per page, default 50
- `offset` (optional): Pagination offset, default 0

Response (200):
```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "companyId": 1,
      "email": "customer@example.com",
      "name": "John Doe",
      ...
    }
  ],
  "pagination": {
    "total": 100,
    "limit": 50,
    "offset": 0
  }
}
```

---

### Get Customer
```
GET /customers/<CUSTOMER_ID>
Authorization: Bearer <JWT>
X-Company-ID: <ID>
```

Response (200):
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "companyId": 1,
    "email": "customer@example.com",
    "name": "John Doe",
    ...
  }
}
```

Error (404):
```json
{
  "error": "Customer not found",
  "statusCode": 404
}
```

---

### Update Customer
```
PUT /customers/<CUSTOMER_ID>
Authorization: Bearer <JWT>
X-Company-ID: <ID>
Content-Type: application/json
```

Request Body (all fields optional):
```json
{
  "email": "newemail@example.com",
  "name": "Jane Doe",
  "phone": "987-654-3210",
  "status": "inactive"
}
```

Response (200):
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "companyId": 1,
    "email": "newemail@example.com",
    "name": "Jane Doe",
    "phone": "987-654-3210",
    "status": "inactive",
    "updatedAt": "2024-01-01T00:01:00Z",
    ...
  }
}
```

---

### Delete Customer
```
DELETE /customers/<CUSTOMER_ID>
Authorization: Bearer <JWT>
X-Company-ID: <ID>
```

Requires: `owner` role

Response (200):
```json
{
  "success": true,
  "message": "Customer deleted successfully"
}
```

Error (403):
```json
{
  "error": "Insufficient permissions",
  "statusCode": 403
}
```

---

## Customer Contact Endpoints

### Add Contact
```
POST /customers/<CUSTOMER_ID>/contacts
Authorization: Bearer <JWT>
X-Company-ID: <ID>
Content-Type: application/json
```

Request Body:
```json
{
  "name": "John Smith",
  "email": "john@example.com",
  "phone": "555-1234",
  "position": "Manager",
  "isPrimary": false
}
```

Response (201):
```json
{
  "success": true,
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "customerId": "550e8400-e29b-41d4-a716-446655440000",
    "name": "John Smith",
    "email": "john@example.com",
    "phone": "555-1234",
    "position": "Manager",
    "isPrimary": false,
    "createdAt": "2024-01-01T00:00:00Z",
    "updatedAt": "2024-01-01T00:00:00Z"
  }
}
```

---

### List Contacts
```
GET /customers/<CUSTOMER_ID>/contacts
Authorization: Bearer <JWT>
X-Company-ID: <ID>
```

Response (200):
```json
{
  "success": true,
  "data": [
    {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "customerId": "550e8400-e29b-41d4-a716-446655440000",
      "name": "John Smith",
      ...
    }
  ]
}
```

---

### Delete Contact
```
DELETE /contacts/<CONTACT_ID>
Authorization: Bearer <JWT>
X-Company-ID: <ID>
```

Response (200):
```json
{
  "success": true,
  "message": "Contact deleted successfully"
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "error": "Validation error: email is required",
  "statusCode": 400
}
```

### 401 Unauthorized
```json
{
  "error": "Invalid or expired token",
  "statusCode": 401
}
```

### 403 Forbidden
```json
{
  "error": "Insufficient permissions",
  "statusCode": 403
}
```

### 404 Not Found
```json
{
  "error": "Customer not found",
  "statusCode": 404
}
```

### 409 Conflict
```json
{
  "error": "Customer with email test@example.com already exists",
  "statusCode": 409
}
```

### 500 Internal Server Error
```json
{
  "error": "Internal server error",
  "statusCode": 500
}
```

---

## Rate Limiting

Currently not implemented. Consider adding for production:
- 100 requests per minute per user
- 1000 requests per minute per IP

---

## Data Types

### Customer
```typescript
{
  id: string;               // UUID
  companyId: number;        // Company identifier
  email: string;            // Unique per company
  name: string;             // Required
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  postalCode?: string;
  country?: string;
  taxId?: string;
  currencyCode: string;     // Default: USD
  website?: string;
  notes?: string;
  status: "active" | "inactive" | "archived";
  createdAt: DateTime;
  updatedAt: DateTime;
  createdBy?: number;       // User ID
  updatedBy?: number;       // User ID
}
```

### CustomerContact
```typescript
{
  id: string;               // UUID
  customerId: string;       // UUID, foreign key to customers
  name: string;             // Required
  email?: string;
  phone?: string;
  position?: string;
  isPrimary: boolean;       // Default: false
  createdAt: DateTime;
  updatedAt: DateTime;
}
```

---

## Examples

### Create Customer with cURL
```bash
curl -X POST http://localhost:8004/api/v1/customers \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "X-Company-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "customer@example.com",
    "name": "John Doe",
    "city": "New York"
  }'
```

### List Customers with Pagination
```bash
curl -X GET "http://localhost:8004/api/v1/customers?limit=20&offset=0" \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "X-Company-ID: 1"
```

### Update Customer
```bash
curl -X PUT http://localhost:8004/api/v1/customers/550e8400-e29b-41d4-a716-446655440000 \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "X-Company-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "inactive",
    "notes": "Updated notes"
  }'
```

### Add Contact
```bash
curl -X POST http://localhost:8004/api/v1/customers/550e8400-e29b-41d4-a716-446655440000/contacts \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "X-Company-ID: 1" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Contact Name",
    "email": "contact@example.com",
    "position": "Manager"
  }'
```
