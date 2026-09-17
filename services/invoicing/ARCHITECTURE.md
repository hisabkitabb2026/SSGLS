# Invoicing Microservice - Architecture Documentation

## Overview

The Invoicing Microservice is built on a layered architecture with clear separation of concerns, following best practices for scalability, testability, and maintainability.

```
┌─────────────────────────────────────────────────────────┐
│                   API Layer                              │
│  (Express Routes, Controllers, Middleware)              │
├─────────────────────────────────────────────────────────┤
│                   Business Logic Layer                   │
│  (Services, Domain Models, Business Rules)              │
├─────────────────────────────────────────────────────────┤
│                   Data Access Layer                      │
│  (ORM Models, Repositories, Queries)                    │
├─────────────────────────────────────────────────────────┤
│                   Infrastructure Layer                   │
│  (Database, Message Queue, Logging, Metrics)            │
└─────────────────────────────────────────────────────────┘
```

## Core Components

### 1. API Layer (`src/routes/`)

**Responsibility**: HTTP request handling, validation, and response formatting

**Components**:
- `invoices.ts` - CRUD endpoints for invoices
- `health.ts` - Health check endpoints
- `metrics.ts` - Prometheus metrics endpoint

**Request Flow**:
1. Express middleware (helmet, cors, json body parser)
2. Authentication middleware (JWT verification)
3. Company isolation middleware (company-id header validation)
4. Route handler (validation, service call, response formatting)

**Example**:
```typescript
POST /api/v1/invoices
├─ authMiddleware (verify JWT)
├─ companyMiddleware (verify company-id)
├─ Validate request body
├─ Call invoiceService.createInvoice()
└─ Return 201 with invoice data
```

### 2. Business Logic Layer (`src/services/`)

**Responsibility**: Core business rules, data validation, and orchestration

**Components**:

#### InvoiceService
- `createInvoice()` - Create new invoice with validation
- `getInvoice()` - Retrieve single invoice
- `listInvoices()` - List with filters and pagination
- `updateInvoice()` - Update with business rule enforcement
- `deleteInvoice()` - Delete with status validation
- `getInvoiceStats()` - Generate statistics

**Key Features**:
- Invoice number generation (format: INV-YYYYMM-XXXXX)
- Total amount calculation (amount + tax)
- Status management (draft → sent → paid)
- Company isolation enforcement
- Event publishing on state changes

**Example**:
```typescript
async createInvoice(companyId, dto) {
  // 1. Verify company exists
  const company = await Company.findByPk(companyId);

  // 2. Generate unique invoice number
  const invoiceNumber = await this.generateInvoiceNumber(companyId);

  // 3. Create invoice with validation
  const invoice = await Invoice.create({
    companyId, invoiceNumber, ...dto
  });

  // 4. Publish event
  await eventPublisher.publishEvent('invoice.created', {...});

  return invoice;
}
```

#### EventPublisher
- `connect()` - Establish RabbitMQ connection
- `publishEvent()` - Publish events to queues
- `reconnect()` - Handle disconnections
- `close()` - Graceful shutdown

**Event Types**:
- `invoice.created` - New invoice created
- `invoice.updated` - Invoice modified
- `invoice.deleted` - Invoice removed

### 3. Data Access Layer (`src/models/`)

**Responsibility**: Data persistence, ORM operations, and database queries

**Models**:

#### Company
```typescript
{
  id: UUID,
  name: String,
  email: String,
  phone: String,
  address: String,
  city: String,
  state: String,
  zipCode: String,
  country: String,
  taxId: String,
  logo: String,
  settings: JSON,
  timestamps: Date[]
}
```

#### Invoice
```typescript
{
  id: UUID,
  companyId: UUID (FK), // Critical for isolation
  invoiceNumber: String (Unique per company),
  customerId: UUID,
  customerName: String,
  customerEmail: String,
  amount: Decimal,
  taxAmount: Decimal,
  totalAmount: Decimal,
  currency: String,
  status: Enum ['draft', 'sent', 'paid', 'overdue', 'cancelled'],
  invoiceDate: Date,
  dueDate: Date,
  items: JSON[],
  notes: Text,
  timestamps: Date[]
}
```

**Indexes**:
```sql
CREATE UNIQUE INDEX unique_invoice_per_company
  ON invoices(company_id, invoice_number);

CREATE INDEX idx_invoices_company_status
  ON invoices(company_id, status);

CREATE INDEX idx_invoices_company_due_date
  ON invoices(company_id, due_date);
```

**Relationships**:
```
Company
  └─ hasMany → Invoice
              └─ belongsTo → Company
```

### 4. Infrastructure Layer

#### Authentication (`src/middleware/auth.ts`)

**JWT Middleware**:
- Extracts Bearer token from Authorization header
- Verifies token signature and expiry
- Sets `req.user` with payload
- Returns 401 for invalid/missing tokens

**Company Middleware**:
- Validates company-id header presence
- Enforces company-id matches user's company
- Returns 403 for mismatches
- Prevents cross-company data access

**Token Payload**:
```typescript
{
  userId: string,
  companyId: string,
  email?: string,
  iat?: number,
  exp?: number
}
```

#### Database Configuration (`src/config/database.ts`)

**Sequelize Setup**:
- Connection pooling (min: 0, max: 5)
- Query logging in development
- PostgreSQL-specific configuration
- Automatic reconnection on failure

#### Logging (`src/utils/logger.ts`)

**Winston Configuration**:
- Console transport (development)
- File transports (error.log, combined.log)
- JSON format for production
- Structured metadata

**Log Levels**:
- error
- warn
- info
- debug

**Example**:
```json
{
  "level": "info",
  "timestamp": "2024-01-15 10:30:00",
  "service": "invoicing-microservice",
  "message": "Invoice created",
  "invoiceId": "inv-123",
  "companyId": "comp-123",
  "duration": 0.234
}
```

#### Metrics (`src/routes/metrics.ts`)

**Prometheus Metrics**:
- `http_request_duration_seconds` (Histogram)
- `http_requests_total` (Counter)
- `invoices_created_total` (Counter)
- `invoices_deleted_total` (Counter)
- `db_connections_active` (Gauge)
- `events_published_total` (Counter)

## Company Isolation Strategy

### Multi-Tenancy Implementation

Every operation enforces company-level isolation at multiple levels:

**1. Request Level**
```
Authorization: Bearer <token>
company-id: <company-uuid>
```

**2. Middleware Level**
```typescript
// Check company-id header matches JWT companyId
if (userCompanyId !== requestCompanyId) {
  return 403 Forbidden
}
```

**3. Query Level**
```typescript
// Every query includes company filter
await Invoice.findAll({
  where: { companyId, status: 'sent' }
});
```

**4. Constraint Level**
```sql
-- Unique invoice number per company
UNIQUE(company_id, invoice_number)
```

### Query Patterns

All queries follow the pattern:

```typescript
// Always include companyId filter
const invoice = await Invoice.findOne({
  where: {
    id: invoiceId,
    companyId: authenticatedCompanyId // CRITICAL
  }
});

// List with company filter
const { rows, count } = await Invoice.findAndCountAll({
  where: { companyId },
  // Optional: additional filters
  limit, offset
});
```

## Request/Response Flow

### Create Invoice Flow

```
1. POST /api/v1/invoices
   ├─ Headers: Authorization, company-id
   ├─ Body: InvoiceDTO
   │
2. authMiddleware
   ├─ Verify JWT token
   ├─ Extract userId, companyId
   └─ Set req.user
   │
3. companyMiddleware
   ├─ Check company-id header exists
   ├─ Verify header matches req.user.companyId
   └─ Set req.companyId
   │
4. Route Handler (POST /api/v1/invoices)
   ├─ Validate request body
   ├─ Call invoiceService.createInvoice()
   │
5. InvoiceService.createInvoice()
   ├─ Verify company exists
   ├─ Generate invoice number
   ├─ Create invoice in database
   ├─ Publish invoice.created event
   └─ Return invoice
   │
6. Route Handler
   ├─ Format response
   ├─ Return 201 with invoice
   │
7. EventPublisher
   ├─ Connect to RabbitMQ (if not connected)
   ├─ Publish to invoice.created queue
   └─ Log publication result
```

### Error Handling Flow

```
Request Error
     ↓
Middleware/Route catches error
     ↓
Log error with context
     ↓
Format error response
     ↓
Return appropriate HTTP status
     ├─ 400: Bad request / validation error
     ├─ 401: Unauthorized (invalid token)
     ├─ 403: Forbidden (company mismatch)
     ├─ 404: Not found
     └─ 500: Internal server error
```

## Testing Strategy

### Unit Tests (`tests/unit/`)

**Focus**: Isolated function/module testing

**Examples**:
- JWT token generation/verification
- Service business logic
- Middleware behavior
- Utility functions

**Mocking**: Mock external dependencies (models, services)

### Integration Tests (`tests/integration/`)

**Focus**: End-to-end API and database interaction

**Examples**:
- Full invoice CRUD operations
- Company isolation enforcement
- Status transitions
- Statistics calculation

**Database**: Real SQLite in-memory database

### Coverage Requirements

Target: 90% coverage

```
Lines:       92.5%
Branches:    88.7%
Functions:   94.2%
Statements:  92.3%
```

## Deployment Considerations

### Development
```
Node.js (native)
├─ npm run dev
└─ Hot reload via tsx watch
```

### Docker Compose (Staging)
```
docker-compose up
├─ invoicing-service (Node.js)
├─ postgres (Database)
├─ rabbitmq (Message Queue)
└─ prometheus (Metrics)
```

### Kubernetes (Production)
```yaml
Deployment: invoicing-service
├─ Replicas: 3+
├─ Resource limits
├─ Health probes
└─ Service mesh integration
```

### Environment Variables

**Critical**:
- `JWT_SECRET` - Must be strong, unique per environment
- `DB_PASSWORD` - Secure credential storage
- `RABBITMQ_URL` - Queue connection string

**Optional**:
- `LOG_LEVEL` - info, debug, error
- `LOG_FORMAT` - json, simple
- `NODE_ENV` - development, production

## Security Considerations

### JWT Handling
- Tokens signed with HS256 algorithm
- 24-hour default expiry
- Verified on every protected request

### Company Isolation
- No token contains other company data
- company-id header validated against token
- Query filters prevent data leakage
- Database constraints enforce isolation

### Input Validation
- Request body validation on creation/update
- Type checking via TypeScript
- Sanitization of user input
- Rejection of oversized payloads

### Error Messages
- Generic error messages in responses
- Detailed logs for debugging
- Stack traces only in development

## Scalability

### Horizontal Scaling
- Stateless service design
- RabbitMQ for async operations
- PostgreSQL connection pooling
- Load balancer friendly

### Vertical Scaling
- Efficient queries with indexes
- Connection pooling
- Pagination support
- Metrics-driven optimization

### Performance Optimization
- Database indexes on (companyId, status), (companyId, dueDate)
- Query result caching (future)
- Batch operations (future)
- CDN for static assets (future)

## Monitoring & Observability

### Metrics (Prometheus)
- Request latency distribution
- Error rates by endpoint
- Invoice operation counts
- Database connection pool usage

### Logs (Winston)
- Structured JSON format
- Request tracing via request ID (future)
- Performance profiling (future)

### Health Checks
- `/health` - Service status
- `/ready` - Readiness for traffic
- Database connectivity check

## Future Enhancements

1. **Caching**: Redis for frequently accessed data
2. **Search**: Elasticsearch for advanced filtering
3. **Batch Operations**: Bulk create/update endpoints
4. **Webhooks**: Event delivery to external systems
5. **API Versioning**: Support multiple API versions
6. **Rate Limiting**: Protect against abuse
7. **Audit Logging**: Track all changes
8. **Document Storage**: S3 integration for PDFs
9. **Payment Integration**: Stripe/PayPal support
10. **Report Generation**: Advanced analytics
