# Invoicing Microservice

A production-ready invoicing microservice built with Node.js, TypeScript, Express, PostgreSQL, and RabbitMQ. Designed for multi-company tenancy with company-level isolation, comprehensive REST API, JWT authentication, event-driven architecture, and detailed monitoring.

## Features

- **Multi-Company Tenancy**: Complete company-level data isolation
- **REST API**: Full CRUD operations for invoices
- **JWT Authentication**: Secure token-based authentication
- **Company Isolation Middleware**: Enforces company_id on all requests
- **Event Publishing**: RabbitMQ integration for async events (invoice.created, invoice.updated, invoice.deleted)
- **Health Checks**: `/health` and `/ready` endpoints
- **Prometheus Metrics**: `/metrics` endpoint with comprehensive statistics
- **Structured Logging**: Winston logger with JSON format support
- **Database Models**: PostgreSQL with Sequelize ORM
- **Invoice Management**: Support for draft, sent, paid, overdue, and cancelled statuses
- **Docker Ready**: Complete Docker Compose setup with PostgreSQL, RabbitMQ, and Prometheus
- **Comprehensive Tests**: 90%+ coverage with unit and integration tests

## Tech Stack

- **Runtime**: Node.js 20+ (Alpine)
- **Language**: TypeScript 5.3+
- **Framework**: Express.js 4.18+
- **Database**: PostgreSQL 16+
- **Message Broker**: RabbitMQ 3.13+
- **ORM**: Sequelize 6.35+
- **Authentication**: JWT (jsonwebtoken)
- **Logging**: Winston 3.11+
- **Metrics**: Prometheus (prom-client)
- **Testing**: Vitest 1.0+ with supertest

## Project Structure

```
invoicing-microservice/
├── src/
│   ├── app.ts                 # Main application entry point
│   ├── config/
│   │   └── database.ts        # PostgreSQL configuration
│   ├── middleware/
│   │   └── auth.ts            # JWT and company isolation middleware
│   ├── models/
│   │   ├── Company.ts         # Company model
│   │   ├── Invoice.ts         # Invoice model
│   │   └── index.ts           # Model associations
│   ├── routes/
│   │   ├── invoices.ts        # Invoice CRUD endpoints
│   │   ├── health.ts          # Health check endpoints
│   │   └── metrics.ts         # Prometheus metrics
│   ├── services/
│   │   ├── invoiceService.ts  # Business logic
│   │   └── eventPublisher.ts  # RabbitMQ event publishing
│   └── utils/
│       ├── logger.ts          # Structured logging
│       └── jwt.ts             # JWT utilities
├── tests/
│   ├── unit/
│   │   ├── jwt.test.ts        # JWT utility tests
│   │   └── invoiceService.test.ts
│   └── integration/
│       └── invoices.test.ts   # API integration tests
├── Dockerfile                 # Container build
├── docker-compose.yml         # Multi-container setup
├── prometheus.yml             # Prometheus config
├── .env.example              # Environment template
├── tsconfig.json             # TypeScript config
├── vitest.config.ts          # Test configuration
└── package.json              # Dependencies
```

## Getting Started

### Prerequisites

- Node.js 20+
- Docker & Docker Compose (for containerized setup)
- PostgreSQL 16+ (if running locally)
- RabbitMQ 3.13+ (if running locally)

### Installation

1. **Clone and Setup**
   ```bash
   git clone <repo>
   cd invoicing-microservice
   cp .env.example .env
   npm install
   ```

2. **Configure Environment**
   ```bash
   # Edit .env with your values
   nano .env
   ```

### Running Locally

1. **With Docker Compose (Recommended)**
   ```bash
   docker-compose up -d
   ```

   Services available:
   - App: http://localhost:8001
   - Health: http://localhost:8001/health
   - Metrics: http://localhost:8001/metrics
   - Prometheus: http://localhost:9091
   - RabbitMQ Management: http://localhost:15672
   - PostgreSQL: localhost:5432

2. **Native Setup**
   ```bash
   # Start PostgreSQL and RabbitMQ manually, then:
   npm run dev
   ```

### Building for Production

```bash
npm run build
docker build -t invoicing-service:latest .
docker run -p 8001:8001 invoicing-service:latest
```

## API Documentation

### Authentication

All endpoints (except `/health` and `/ready`) require JWT in the `Authorization` header:

```bash
Authorization: Bearer <token>
```

And a company ID in the `company-id` header:

```bash
company-id: <company-uuid>
```

### Generate Test Token

```bash
curl -X POST http://localhost:8001/auth/token \
  -H "Content-Type: application/json" \
  -d '{
    "userId": "user-123",
    "companyId": "company-123",
    "email": "user@example.com"
  }'
```

### Endpoints

#### Health Checks
```bash
GET /health       # Service health status
GET /ready        # Readiness check
GET /metrics      # Prometheus metrics
```

#### Invoices
```bash
# Create Invoice
POST /api/v1/invoices
Content-Type: application/json
{
  "customerId": "cust-123",
  "customerName": "John Doe",
  "customerEmail": "john@example.com",
  "amount": 1000,
  "taxAmount": 100,
  "currency": "USD",
  "invoiceDate": "2024-01-15",
  "dueDate": "2024-02-15",
  "items": [
    {
      "description": "Service",
      "quantity": 1,
      "unitPrice": 1000,
      "totalPrice": 1000
    }
  ],
  "notes": "Invoice notes"
}

# Get All Invoices
GET /api/v1/invoices?status=sent&limit=20&offset=0

# Get Invoice by ID
GET /api/v1/invoices/:id

# Update Invoice
PATCH /api/v1/invoices/:id
{
  "status": "sent",
  "customerEmail": "newemail@example.com"
}

# Delete Invoice
DELETE /api/v1/invoices/:id

# Filter by Status
GET /api/v1/invoices/status/:status

# Get Statistics
GET /api/v1/invoices/stats/summary
```

## Database

### Models

#### Company
- `id` (UUID, Primary Key)
- `name` (String)
- `email` (String, Optional)
- `phone` (String, Optional)
- `address` (String, Optional)
- `city` (String, Optional)
- `state` (String, Optional)
- `zipCode` (String, Optional)
- `country` (String, Optional)
- `taxId` (String, Optional)
- `logo` (String, Optional)
- `settings` (JSON)
- `timestamps` (createdAt, updatedAt)

#### Invoice
- `id` (UUID, Primary Key)
- `companyId` (UUID, Foreign Key) - **Company Isolation**
- `invoiceNumber` (String, Unique per company)
- `customerId` (UUID)
- `customerName` (String)
- `customerEmail` (String, Optional)
- `amount` (Decimal)
- `taxAmount` (Decimal)
- `totalAmount` (Decimal)
- `currency` (String, Default: USD)
- `status` (Enum: draft, sent, paid, overdue, cancelled)
- `invoiceDate` (Date)
- `dueDate` (Date)
- `items` (JSON Array)
- `notes` (Text, Optional)
- `timestamps` (createdAt, updatedAt)

### Indexes
- `(companyId, invoiceNumber)` - Unique constraint
- `(companyId, status)` - Filter queries
- `(companyId, dueDate)` - Due date queries

## RabbitMQ Events

The service publishes events to RabbitMQ queues:

```javascript
// Invoice Created
{
  "eventType": "invoice.created",
  "data": {
    "invoiceId": "inv-123",
    "invoiceNumber": "INV-202401-00001",
    "companyId": "comp-123",
    "customerId": "cust-123",
    "customerName": "John Doe",
    "amount": 1100
  },
  "timestamp": "2024-01-15T10:30:00Z",
  "companyId": "comp-123"
}

// Invoice Updated
{
  "eventType": "invoice.updated",
  "data": {
    "invoiceId": "inv-123",
    "invoiceNumber": "INV-202401-00001",
    "companyId": "comp-123",
    "changes": { "status": "sent" }
  },
  "timestamp": "2024-01-15T10:30:00Z",
  "companyId": "comp-123"
}

// Invoice Deleted
{
  "eventType": "invoice.deleted",
  "data": {
    "invoiceId": "inv-123",
    "invoiceNumber": "INV-202401-00001",
    "companyId": "comp-123"
  },
  "timestamp": "2024-01-15T10:30:00Z",
  "companyId": "comp-123"
}
```

Queues:
- `invoice.created` - New invoice created
- `invoice.updated` - Invoice updated
- `invoice.deleted` - Invoice deleted

## Logging

Structured logging with Winston:

```json
{
  "level": "info",
  "timestamp": "2024-01-15 10:30:00",
  "service": "invoicing-microservice",
  "message": "Invoice created",
  "invoiceId": "inv-123",
  "companyId": "comp-123"
}
```

Log files:
- `logs/combined.log` - All logs
- `logs/error.log` - Errors only

## Metrics

Prometheus metrics at `/metrics`:

- `http_request_duration_seconds` - HTTP request latency
- `http_requests_total` - Total HTTP requests
- `invoices_created_total` - Total invoices created
- `invoices_deleted_total` - Total invoices deleted
- `db_connections_active` - Active database connections
- `events_published_total` - Events published to RabbitMQ

## Testing

### Run Tests
```bash
npm test                  # All tests
npm run test:coverage    # With coverage report (90%+ required)
npm run test:integration # Integration tests only
```

### Coverage
Target: 90%+ coverage across all files

```bash
npm run test:coverage

# Output example:
# ┌─────────────────────────────────────────────────────┐
# │ % Stmts  │ % Branches │ % Funcs │ % Lines │ Uncovered │
# ├──────────┼───────────┼────────┼────────┤
# │ 92.5     │ 88.7      │ 94.2   │ 92.3   │ ...       │
# └─────────────────────────────────────────────────────┘
```

## Deployment

### Docker Compose (Development/Staging)
```bash
docker-compose up -d
```

### Kubernetes (Production)
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: invoicing-service
spec:
  replicas: 3
  selector:
    matchLabels:
      app: invoicing-service
  template:
    metadata:
      labels:
        app: invoicing-service
    spec:
      containers:
      - name: invoicing-service
        image: invoicing-service:latest
        ports:
        - containerPort: 8001
        env:
        - name: DB_HOST
          valueFrom:
            configMapKeyRef:
              name: invoicing-config
              key: db-host
        livenessProbe:
          httpGet:
            path: /health
            port: 8001
          initialDelaySeconds: 10
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8001
          initialDelaySeconds: 5
          periodSeconds: 5
```

### Environment Variables

```bash
# Server
NODE_ENV=production
PORT=8001
SERVICE_NAME=invoicing-microservice
LOG_LEVEL=info

# Database
DB_HOST=postgres.example.com
DB_PORT=5432
DB_NAME=invoicing_db
DB_USER=postgres
DB_PASSWORD=<secure-password>

# JWT
JWT_SECRET=<very-long-random-secret>
JWT_EXPIRY=24h

# RabbitMQ
RABBITMQ_URL=amqp://user:pass@rabbitmq.example.com:5672

# Monitoring
METRICS_ENABLED=true
METRICS_PORT=9090
LOG_FORMAT=json
```

## Company Isolation

**Critical**: All invoices are automatically isolated by `company_id`:

1. **Request Validation**: `company-id` header required on all invoice endpoints
2. **Database Queries**: Every query includes `WHERE companyId = ?`
3. **API Authorization**: Invalid company access returns 403
4. **Model Constraints**: Unique index on `(companyId, invoiceNumber)`

```sql
-- Example: User can only see their company's invoices
SELECT * FROM invoices WHERE company_id = ? AND status = 'sent'
```

## Error Handling

All endpoints return standardized JSON responses:

```json
{
  "status": "success|error",
  "data": {},
  "message": "Human-readable message"
}
```

HTTP Status Codes:
- `200` - OK
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden (Company mismatch)
- `404` - Not Found
- `500` - Internal Server Error
- `503` - Service Unavailable

## Troubleshooting

### Database Connection Failed
```bash
# Check PostgreSQL is running
docker-compose logs postgres

# Verify connection string in .env
docker-compose exec invoicing-service npm run migrate
```

### RabbitMQ Not Publishing
```bash
# Check RabbitMQ is running
docker-compose logs rabbitmq

# Monitor queue
docker-compose exec rabbitmq rabbitmqctl list_queues
```

### Metrics Not Appearing
```bash
# Check Prometheus scrape config
curl http://localhost:9091/api/v1/targets

# Check service metrics endpoint
curl http://localhost:8001/metrics
```

## License

MIT - See LICENSE file

## Support

For issues and feature requests, please use the GitHub issues tracker.
