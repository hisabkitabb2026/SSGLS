# Customer Microservice

A production-ready Node.js microservice for customer management with PostgreSQL, RabbitMQ, JWT authentication, and comprehensive test coverage (90%+).

## Features

- **REST API**: Complete CRUD operations for customers and contacts
- **Multi-Tenancy**: Company-level data isolation via `company_id`
- **Authentication**: JWT-based with role-based access control (RBAC)
- **Event Publishing**: RabbitMQ integration for async event handling
- **Database**: PostgreSQL with connection pooling
- **Health Checks**: Built-in endpoints for Kubernetes and load balancers
- **Metrics**: Real-time performance and resource monitoring
- **Structured Logging**: Pino-based structured JSON logging
- **Error Handling**: Comprehensive error handling with meaningful messages
- **Testing**: 90%+ code coverage with unit and integration tests
- **Docker**: Complete Docker setup with docker-compose

## Getting Started

### Prerequisites

- Node.js 18+
- Docker and Docker Compose
- PostgreSQL 12+ (if running locally)
- RabbitMQ (if running locally)

### Installation

1. Clone the repository:
```bash
cd customer-service
```

2. Install dependencies:
```bash
npm install
```

3. Set up environment variables:
```bash
cp .env.example .env
```

4. Build TypeScript:
```bash
npm run build
```

### Running the Service

#### Using Docker Compose (Recommended)

```bash
docker-compose up -d
```

This will start:
- Customer Service on port 8004
- PostgreSQL on port 5432
- RabbitMQ on ports 5672 (AMQP) and 15672 (Management UI)
- Adminer on port 8080 (for database management)

#### Running Locally

```bash
# Start the development server
npm run dev

# Or in production
npm run build
npm start
```

## Environment Configuration

See `.env.example` for all available options:

```env
# Server
PORT=8004
NODE_ENV=development

# Database
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=customer_service
DB_USERNAME=customer_user
DB_PASSWORD=secure_password

# JWT
JWT_SECRET=your_secret_key
JWT_EXPIRY=7d

# RabbitMQ
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672

# Logging
LOG_LEVEL=info
```

## API Endpoints

### Health & Monitoring

- `GET /health` - Health check endpoint
- `GET /ready` - Readiness probe
- `GET /metrics` - Service metrics (memory, uptime, request stats)

### Customers

- `POST /api/v1/customers` - Create customer (requires auth, owner/admin role)
- `GET /api/v1/customers` - List customers (paginated)
- `GET /api/v1/customers/:id` - Get customer details
- `PUT /api/v1/customers/:id` - Update customer (requires auth, owner/admin role)
- `DELETE /api/v1/customers/:id` - Delete customer (requires auth, owner role)

### Customer Contacts

- `POST /api/v1/customers/:customerId/contacts` - Add contact
- `GET /api/v1/customers/:customerId/contacts` - List contacts
- `DELETE /api/v1/contacts/:contactId` - Delete contact

## Authentication

The service uses JWT tokens for authentication. All protected endpoints require:

```
Authorization: Bearer <jwt_token>
X-Company-ID: <company_id>
```

### Token Payload

```json
{
  "userId": 123,
  "companyId": 1,
  "email": "user@example.com",
  "role": "owner|admin|viewer",
  "iat": 1234567890,
  "exp": 1234571490
}
```

### Roles

- **owner** - Full access including delete operations
- **admin** - Can create, read, update (no delete)
- **viewer** - Read-only access

## Database Schema

### customers table
```sql
- id (UUID, PK)
- company_id (INT, required)
- email (VARCHAR, unique)
- name (VARCHAR)
- phone, address, city, state, postal_code, country
- tax_id, currency_code, website, notes
- status (active|inactive|archived)
- created_at, updated_at, created_by, updated_by
```

### customer_contacts table
```sql
- id (UUID, PK)
- customer_id (UUID, FK to customers)
- name, email, phone, position
- is_primary (boolean)
- created_at, updated_at
```

### customer_events table
```sql
- id (SERIAL, PK)
- customer_id, company_id, event_type
- event_data (JSONB)
- created_at
```

## RabbitMQ Events

The service publishes events to the `customers` exchange:

- `customer.created` - When a customer is created
- `customer.updated` - When a customer is updated
- `customer.deleted` - When a customer is deleted
- `customer.contact_added` - When a contact is added

Event payload:
```json
{
  "eventType": "created|updated|deleted|contact_added",
  "timestamp": "2024-01-01T00:00:00Z",
  "data": {
    "customerId": "uuid",
    "companyId": 1,
    "email": "test@example.com",
    "name": "Customer Name"
  }
}
```

## Testing

### Run All Tests

```bash
npm test
```

### Run Tests with Coverage

```bash
npm run test:coverage
```

### Watch Mode

```bash
npm run test:watch
```

### Test Structure

- **Unit Tests** (`tests/unit/`):
  - `CustomerService.test.ts` - Service business logic
  - `JWT.test.ts` - JWT token generation and verification
  - `Metrics.test.ts` - Metrics collection

- **Integration Tests** (`tests/integration/`):
  - `CustomerRoutes.test.ts` - Full API endpoint tests
  - `Authentication.test.ts` - Auth middleware and RBAC

### Coverage Threshold

The project enforces 90% coverage for:
- Branches
- Functions
- Lines
- Statements

## Logging

The service uses Pino for structured logging:

```typescript
import { logger } from './utils/logger';

logger.info({ customerId: 'uuid' }, 'Customer created');
logger.error({ error }, 'Database error');
logger.warn({ endpoint: '/api/v1' }, 'Slow response');
```

Log output in production (JSON):
```json
{
  "level": "INFO",
  "time": "2024-01-01T00:00:00Z",
  "service": "customer-service",
  "customerId": "uuid",
  "msg": "Customer created"
}
```

## Metrics

Access metrics at `GET /metrics`:

```json
{
  "success": true,
  "data": {
    "timestamp": "2024-01-01T00:00:00Z",
    "uptime": 3600,
    "memory": {
      "heapUsed": 50,
      "heapTotal": 100,
      "external": 5,
      "rss": 120
    },
    "http": {
      "requests": 1500,
      "errors": 12,
      "avgResponseTime": 45
    },
    "database": {
      "queries": 3000,
      "errors": 2,
      "avgQueryTime": 25
    },
    "events": {
      "published": 500,
      "failed": 1
    }
  }
}
```

## Error Handling

The service returns standardized error responses:

```json
{
  "error": "Customer with email test@example.com already exists",
  "statusCode": 409
}
```

Common status codes:
- `400` - Bad request (validation error)
- `401` - Unauthorized (missing/invalid JWT)
- `403` - Forbidden (insufficient permissions)
- `404` - Not found
- `409` - Conflict (duplicate email)
- `500` - Internal server error

## Development

### Code Style

- TypeScript with strict mode
- ESLint configuration included
- Prettier formatting

```bash
npm run lint
npm run lint:fix
```

### Database Migrations

The service auto-initializes the schema on startup. For manual migrations:

```bash
npm run migrate
```

## Docker Deployment

### Build Image

```bash
docker build -t customer-service:latest .
```

### Push to Registry

```bash
docker tag customer-service:latest myregistry.com/customer-service:latest
docker push myregistry.com/customer-service:latest
```

### Kubernetes Deployment

Health checks are configured for Kubernetes:
- Liveness probe: `GET /health`
- Readiness probe: `GET /ready`

## Performance Considerations

- Connection pooling (min: 2, max: 10)
- Indexed queries on `company_id`, `email`, `status`, `created_at`
- Pagination with default limit of 50
- Request/response compression
- CORS enabled

## Security

- Helmet for HTTP headers
- CORS restrictions (configurable)
- JWT token validation on all protected routes
- Company ID isolation enforced
- Input validation with Joi
- SQL injection prevention via parameterized queries
- HTTPS support (with reverse proxy)

## Troubleshooting

### Database Connection Issues

```
Error: connect ECONNREFUSED 127.0.0.1:5432
```

Solution: Ensure PostgreSQL is running and credentials in `.env` are correct.

### RabbitMQ Connection Issues

```
Error: ECONNREFUSED 127.0.0.1:5672
```

Solution: Ensure RabbitMQ is running or set `RABBITMQ_HOST` to correct hostname.

### Metrics Shows Zero Values

This is normal on fresh startup. Metrics accumulate over time.

## License

MIT

## Support

For issues and questions:
1. Check the logs: `docker-compose logs customer-service`
2. Check database: `http://localhost:8080` (Adminer)
3. Check RabbitMQ: `http://localhost:15672` (guest/guest)

## Contributing

1. Create a feature branch
2. Add tests for new features
3. Ensure 90%+ test coverage
4. Run linter and tests before pushing
5. Create a pull request

## Changelog

### v1.0.0 (2024)
- Initial release
- Full CRUD operations for customers and contacts
- JWT authentication with RBAC
- RabbitMQ event publishing
- 90%+ test coverage
- Docker deployment ready
