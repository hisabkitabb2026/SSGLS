# Quick Start Guide

Get the Expense Microservice running in 5 minutes.

## Option 1: Docker (Recommended)

### Prerequisites
- Docker Desktop (or Docker + Docker Compose)

### Steps

1. **Start services**
   ```bash
   docker-compose up -d
   ```

2. **Wait for startup** (30 seconds)
   ```bash
   # Check service health
   curl http://localhost:8002/health
   ```

3. **Generate token**
   ```bash
   curl -X POST "http://localhost:8002/auth/token?user_id=1&company_id=1&permissions=admin,approve_expenses"
   ```

   Copy the `access_token` from response.

4. **Create expense**
   ```bash
   curl -X POST "http://localhost:8002/api/v1/companies/1/expenses" \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
       "category": "travel",
       "amount": 150.00,
       "expense_date": "2024-01-15T10:30:00Z",
       "merchant_name": "Airlines Inc",
       "description": "Business trip"
     }'
   ```

5. **List expenses**
   ```bash
   curl "http://localhost:8002/api/v1/companies/1/expenses" \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

## Option 2: Local Development

### Prerequisites
- Python 3.11+
- PostgreSQL 12+
- RabbitMQ 3.8+

### Steps

1. **Clone repository**
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
   pip install -r requirements.txt
   ```

4. **Configure database** (edit `.env`)
   ```bash
   DATABASE_URL=postgresql://postgres:postgres@localhost:5432/expense_service
   ```

5. **Start services**
   - PostgreSQL: `psql -U postgres -c "CREATE DATABASE expense_service;"`
   - RabbitMQ: `rabbitmq-server` (or `brew services start rabbitmq`)

6. **Initialize database**
   ```bash
   python -c "
   from app.models import DatabaseManager
   from app.config import get_settings
   settings = get_settings()
   DatabaseManager.initialize(settings.database_url)
   DatabaseManager.create_all()
   "
   ```

7. **Run server**
   ```bash
   uvicorn app.main:app --host 0.0.0.0 --port 8002 --reload
   ```

8. **Test API** (see Option 1 steps 3-5)

## Common Commands

### Development
```bash
make test           # Run tests
make test-cov       # Test coverage (90%+)
make lint          # Code quality checks
make format        # Auto-format code
make run           # Start dev server
```

### Docker
```bash
make docker-up     # Start all services
make docker-logs   # View logs
make docker-down   # Stop services
```

### Database
```bash
make db-init       # Initialize schema
make db-migrate    # Run migrations
```

## API Examples

### Authentication
```bash
# Generate token (1 user, company 1, admin role)
TOKEN=$(curl -s -X POST \
  "http://localhost:8002/auth/token?user_id=1&company_id=1&permissions=admin,approve_expenses" \
  | jq -r '.access_token')

echo "Token: $TOKEN"
```

### Create Expense
```bash
curl -X POST "http://localhost:8002/api/v1/companies/1/expenses" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "category": "travel",
    "amount": 150.50,
    "currency_code": "USD",
    "merchant_name": "United Airlines",
    "description": "Flight to NYC",
    "expense_date": "2024-01-15T14:30:00Z",
    "payment_method": "credit_card",
    "receipt_url": "https://example.com/receipt.pdf"
  }'
```

Response:
```json
{
  "id": 1,
  "company_id": 1,
  "user_id": 1,
  "category": "travel",
  "amount": 150.50,
  "currency_code": "USD",
  "merchant_name": "United Airlines",
  "description": "Flight to NYC",
  "expense_date": "2024-01-15T14:30:00Z",
  "status": "pending",
  "created_at": "2024-01-15T15:00:00Z",
  "updated_at": "2024-01-15T15:00:00Z"
}
```

### List Expenses
```bash
# All expenses
curl "http://localhost:8002/api/v1/companies/1/expenses" \
  -H "Authorization: Bearer $TOKEN"

# Filter by category
curl "http://localhost:8002/api/v1/companies/1/expenses?category=travel" \
  -H "Authorization: Bearer $TOKEN"

# Filter by status
curl "http://localhost:8002/api/v1/companies/1/expenses?status=pending" \
  -H "Authorization: Bearer $TOKEN"

# Pagination
curl "http://localhost:8002/api/v1/companies/1/expenses?skip=0&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

### Update Expense
```bash
curl -X PATCH "http://localhost:8002/api/v1/companies/1/expenses/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 160.00,
    "description": "Flight to NYC (updated)"
  }'
```

### Approve Expense
```bash
curl -X POST "http://localhost:8002/api/v1/companies/1/expenses/1/approve" \
  -H "Authorization: Bearer $TOKEN"
```

### Reject Expense
```bash
curl -X POST "http://localhost:8002/api/v1/companies/1/expenses/1/reject" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "rejected",
    "comment": "Invalid receipt"
  }'
```

### Get Statistics
```bash
curl "http://localhost:8002/api/v1/companies/1/expenses/statistics/summary" \
  -H "Authorization: Bearer $TOKEN"
```

Response:
```json
{
  "total_expenses": 1,
  "total_amount": 150.50,
  "status_breakdown": {
    "pending": 0,
    "approved": 1,
    "rejected": 0,
    "paid": 0
  },
  "category_breakdown": {
    "travel": {
      "count": 1,
      "total": 150.50
    }
  }
}
```

### Delete Expense
```bash
curl -X DELETE "http://localhost:8002/api/v1/companies/1/expenses/1" \
  -H "Authorization: Bearer $TOKEN"
```

## Health & Monitoring

### Health Check
```bash
curl http://localhost:8002/health
```

Response:
```json
{
  "status": "healthy",
  "service_name": "expense-service",
  "version": "1.0.0",
  "database": "healthy",
  "rabbitmq": "healthy"
}
```

### Metrics
```bash
curl http://localhost:8002/metrics
```

## UI Tools

### RabbitMQ Management
- URL: http://localhost:15672
- Username: `guest`
- Password: `guest`

### PgAdmin (optional)
```bash
# Enable with
docker-compose --profile debug up -d

# Access at http://localhost:5050
# Email: admin@example.com
# Password: admin
```

## Troubleshooting

### Port Already in Use
```bash
# On macOS/Linux - find process
lsof -i :8002
kill -9 <PID>

# Change port in docker-compose.yml or .env
SERVICE_PORT=8004
```

### Database Connection Error
```bash
# Check PostgreSQL
psql -U postgres -h localhost

# Initialize database
make db-init
```

### RabbitMQ Connection Error
```bash
# Restart RabbitMQ
docker restart expense-rabbitmq

# Or start service
rabbitmq-server
```

### Tests Failing
```bash
# Run with verbose output
pytest -vv tests/

# Run specific test
pytest -vv tests/test_api.py::TestExpenseCreation::test_create_expense_success

# Check coverage
pytest --cov=app --cov-report=term-missing
```

## Next Steps

1. **Read Documentation**: See `README.md` for full API docs
2. **Understand Architecture**: Review `app/` directory structure
3. **Run Tests**: Execute `make test-cov` to verify setup
4. **Deploy**: Follow `DEPLOYMENT.md` for production setup
5. **Integrate**: Connect frontend to API endpoints

## Getting Help

- **API Issues**: Check `README.md` API Endpoints section
- **Deployment**: See `DEPLOYMENT.md`
- **Testing**: Review `tests/` for examples
- **Logs**: Check `docker-compose logs expense-service`
- **RabbitMQ**: Visit http://localhost:15672 (guest/guest)

## Production Deployment

For production setup:
1. Read `DEPLOYMENT.md`
2. Update `JWT_SECRET_KEY` in `.env`
3. Configure PostgreSQL for your environment
4. Set up RabbitMQ with proper security
5. Enable HTTPS/TLS
6. Configure monitoring and alerting
