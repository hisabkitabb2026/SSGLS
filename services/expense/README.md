# Expense Microservice

A production-ready expense tracking and management microservice built with FastAPI, PostgreSQL, and RabbitMQ. Designed for the InvoiceShelf platform with multi-tenant support and event-driven architecture.

## Features

- **REST API** - Complete CRUD operations for expense management
- **Multi-Tenancy** - Company-level isolation with `company_id` enforcement
- **JWT Authentication** - Secure token-based authentication with role-based access control
- **Event Publishing** - RabbitMQ integration for event-driven workflows
- **Database Models** - SQLAlchemy ORM with PostgreSQL support
- **Health Checks** - Comprehensive health and metrics endpoints
- **Structured Logging** - JSON-formatted logs with correlation IDs
- **Docker Support** - Complete Docker and Docker Compose setup
- **Test Coverage** - Unit and integration tests with >90% coverage
- **Metrics** - Prometheus-compatible metrics endpoint

## Quick Start

### Prerequisites

- Python 3.11+
- PostgreSQL 12+
- RabbitMQ 3.8+
- Docker & Docker Compose (optional)

### Local Development

1. **Clone and setup**
   ```bash
   cd expense-service
   cp .env.example .env
   ```

2. **Create virtual environment**
   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows: venv\Scripts\activate
   ```

3. **Install dependencies**
   ```bash
   make install-dev
   ```

4. **Configure environment**
   ```bash
   # Edit .env with your local database and RabbitMQ settings
   nano .env
   ```

5. **Initialize database**
   ```bash
   make db-init
   ```

6. **Run development server**
   ```bash
   make run
   # Service available at http://localhost:8002
   ```

### Docker Deployment

1. **Start all services**
   ```bash
   make docker-up
   ```

   This starts:
   - PostgreSQL (port 5432)
   - RabbitMQ (port 5672, management UI at 15672)
   - Expense Service (port 8002)
   - PgAdmin (port 5050, optional with `docker-compose up -d`)

2. **View logs**
   ```bash
   make docker-logs
   ```

3. **Stop services**
   ```bash
   make docker-down
   ```

## API Endpoints

### Authentication

Generate JWT token:
```bash
POST /auth/token?user_id=1&company_id=1&permissions=admin,approve_expenses
```

Response:
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "bearer",
  "expires_in": 86400
}
```

### Expense Management

All endpoints require `Authorization: Bearer <token>` header.

#### Create Expense
```bash
POST /api/v1/companies/{company_id}/expenses
```

Request:
```json
{
  "category": "travel",
  "amount": 150.00,
  "currency_code": "USD",
  "description": "Business trip",
  "merchant_name": "Airlines Inc",
  "expense_date": "2024-01-15T10:30:00Z",
  "payment_method": "credit_card",
  "receipt_url": "https://example.com/receipt.pdf",
  "metadata": {"project_id": 123}
}
```

#### Get Expense
```bash
GET /api/v1/companies/{company_id}/expenses/{expense_id}
```

#### List Expenses
```bash
GET /api/v1/companies/{company_id}/expenses
  ?skip=0
  &limit=50
  &category=travel
  &status=pending
  &user_id=1
```

Response:
```json
{
  "items": [...],
  "total": 100,
  "skip": 0,
  "limit": 50,
  "has_more": true
}
```

#### Update Expense
```bash
PATCH /api/v1/companies/{company_id}/expenses/{expense_id}
```

Request (all fields optional):
```json
{
  "amount": 200.00,
  "description": "Updated description",
  "status": "approved"
}
```

#### Delete Expense
```bash
DELETE /api/v1/companies/{company_id}/expenses/{expense_id}
```

#### Approve Expense
```bash
POST /api/v1/companies/{company_id}/expenses/{expense_id}/approve
```

Requires `approve_expenses` permission.

#### Reject Expense
```bash
POST /api/v1/companies/{company_id}/expenses/{expense_id}/reject
```

Request:
```json
{
  "status": "rejected",
  "comment": "Invalid receipt"
}
```

Requires `approve_expenses` permission.

#### Get Statistics
```bash
GET /api/v1/companies/{company_id}/expenses/statistics/summary
  ?user_id=1  # Optional, admin only
```

### System Endpoints

#### Health Check
```bash
GET /health
```

Response:
```json
{
  "status": "healthy",
  "service_name": "expense-service",
  "version": "1.0.0",
  "timestamp": "2024-01-15T10:30:00Z",
  "database": "healthy",
  "rabbitmq": "healthy"
}
```

#### Metrics
```bash
GET /metrics
```

Prometheus-compatible metrics.

## Database Schema

### Expenses Table
```sql
CREATE TABLE expenses (
  id INTEGER PRIMARY KEY,
  company_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  category VARCHAR(50) NOT NULL,
  amount NUMERIC(13,2) NOT NULL,
  currency_code VARCHAR(3) NOT NULL DEFAULT 'USD',
  description TEXT,
  merchant_name VARCHAR(255),
  expense_date TIMESTAMP NOT NULL,
  status VARCHAR(20) DEFAULT 'pending',
  payment_method VARCHAR(50),
  receipt_url VARCHAR(500),
  attachments JSON,
  metadata JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Indexes:
- `company_id, user_id, expense_date`
- `company_id, status`
- `company_id, category`

### Expense Approvals Table
```sql
CREATE TABLE expense_approvals (
  id INTEGER PRIMARY KEY,
  expense_id INTEGER NOT NULL,
  company_id INTEGER NOT NULL,
  approver_id INTEGER NOT NULL,
  approval_level INTEGER DEFAULT 1,
  status VARCHAR(20) DEFAULT 'pending',
  comment TEXT,
  decision_date TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Event Publishing

Events published to RabbitMQ with format:
```json
{
  "event_type": "expense.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "expense_id": 1,
    "company_id": 1,
    "user_id": 1
  }
}
```

### Event Types

- `expense.created` - New expense created
- `expense.updated` - Expense updated
- `expense.deleted` - Expense deleted
- `expense.approved` - Expense approved
- `expense.rejected` - Expense rejected

## Testing

### Run Tests
```bash
make test
```

### Test Coverage (90%+ target)
```bash
make test-cov
```

Opens `htmlcov/index.html` with detailed coverage report.

### Test Structure

- `tests/conftest.py` - Shared fixtures and test configuration
- `tests/test_api.py` - API endpoint tests (~500 lines)
- `tests/test_services.py` - Business logic tests (~350 lines)

Coverage by module:
- `app/models.py` - 95%+
- `app/services/expense_service.py` - 92%+
- `app/api/routes.py` - 90%+
- `app/middleware/jwt_middleware.py` - 88%+
- `app/services/event_publisher.py` - 85%+

## Configuration

### Environment Variables

See `.env.example` for all available options.

**Critical settings:**
- `DATABASE_URL` - PostgreSQL connection string
- `JWT_SECRET_KEY` - Secret for JWT signing (change in production)
- `RABBITMQ_*` - RabbitMQ connection details
- `SERVICE_ENV` - `development` or `production`
- `DEBUG` - Enable debug mode (development only)

### Development Mode

```bash
SERVICE_ENV=development
DEBUG=True
LOG_LEVEL=DEBUG
LOG_FORMAT=console  # Pretty-printed logs
```

### Production Mode

```bash
SERVICE_ENV=production
DEBUG=False
LOG_LEVEL=INFO
LOG_FORMAT=json
JWT_SECRET_KEY=<strong-random-key>
```

## Security Considerations

1. **JWT Secret** - Generate strong random key:
   ```bash
   python -c "import secrets; print(secrets.token_urlsafe(32))"
   ```

2. **Company Isolation** - All queries filtered by `company_id` from JWT token

3. **Permission Checks** - Approval endpoints require `approve_expenses` permission

4. **CORS** - Configured for allowed origins only

5. **Rate Limiting** - Implement at API Gateway or reverse proxy level

## Monitoring & Logging

### Structured Logging

All logs in JSON format with:
- `timestamp` - ISO format
- `level` - DEBUG, INFO, WARNING, ERROR
- `logger` - Module name
- `message` - Human-readable message
- Custom fields - Context-specific data

Example:
```json
{
  "timestamp": "2024-01-15T10:30:00.123Z",
  "level": "info",
  "logger": "app.services.expense_service",
  "message": "expense_created",
  "expense_id": 1,
  "company_id": 1,
  "amount": "150.00"
}
```

### Metrics

Prometheus metrics available at `/metrics`:
- `service_info` - Service version
- `service_requests_total` - Total requests
- `service_request_duration_seconds` - Request latency
- `service_errors_total` - Error count

## Development

### Code Style

- **Python**: PEP 8 with Black formatter
- **Line length**: 100 characters
- **Type hints**: Required for all functions

```bash
make lint      # Check style
make format    # Auto-format code
```

### Adding New Endpoints

1. Add schema in `app/schemas.py`
2. Add route in `app/api/routes.py`
3. Add business logic in `app/services/expense_service.py`
4. Add tests in `tests/test_api.py` and `tests/test_services.py`
5. Ensure 90%+ coverage

### Database Migrations

Using Alembic:
```bash
alembic init alembic
alembic revision --autogenerate -m "Add new table"
alembic upgrade head
```

## Troubleshooting

### Database Connection Error
```
psycopg2.OperationalError: could not connect to server
```
- Verify PostgreSQL is running
- Check `DATABASE_URL` in `.env`
- Ensure database exists: `createdb expense_service`

### RabbitMQ Connection Error
```
pika.exceptions.AMQPConnectionError
```
- RabbitMQ may be unavailable (warning only in development)
- Check `RABBITMQ_HOST` and port
- In docker-compose, ensure `rabbitmq` service is running

### JWT Validation Error
```
Token has expired
```
- Token expired, generate new one with `/auth/token`
- Check `JWT_EXPIRATION_HOURS` setting

### Company Isolation Error
```
Not authorized to access this company's data
```
- Token's `company_id` doesn't match request URL
- Non-admin users can only access their own company

## Performance Tips

1. **Database Indexes** - Already created on common filter columns
2. **Pagination** - Always use skip/limit for listing
3. **Query Optimization** - Use `select_in_load` for relationships
4. **Connection Pooling** - Configured with 20 connections
5. **Caching** - Consider Redis for frequently accessed data

## Contributing

1. Create feature branch: `git checkout -b feature/name`
2. Make changes and write tests
3. Ensure >90% coverage: `make test-cov`
4. Format code: `make format`
5. Lint code: `make lint`
6. Create pull request with description

## License

MIT License - See LICENSE file for details

## Support

For issues and questions:
1. Check [API Documentation](#api-endpoints)
2. Review test examples in `tests/`
3. Check logs: `docker-compose logs expense-service`
4. Check RabbitMQ: http://localhost:15672 (guest/guest)
5. Check database: Use PgAdmin at http://localhost:5050
