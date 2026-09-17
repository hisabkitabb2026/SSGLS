# Transport Microservice

A complete, production-ready Transport microservice built with Python, FastAPI, PostgreSQL, and RabbitMQ. Includes JWT authentication, multi-tenancy support, event publishing, health checks, metrics, and comprehensive testing.

## Features

- **FastAPI Framework**: Modern async REST API with automatic OpenAPI documentation
- **PostgreSQL Database**: Multi-tenant data isolation with company_id isolation
- **JWT Authentication**: Secure token-based authentication middleware
- **RabbitMQ Integration**: Event publishing for asynchronous processing
- **Prometheus Metrics**: Production-grade monitoring and metrics collection
- **Structured Logging**: JSON formatted logs for better observability
- **Health Checks**: Application and dependency health monitoring
- **REST CRUD Operations**: Complete Create, Read, Update, Delete endpoints
- **Multi-tenancy**: Company-level data isolation built-in
- **Comprehensive Tests**: Unit and integration tests with 90%+ coverage
- **Docker Support**: Dockerfile and docker-compose for easy deployment

## Project Structure

```
transport-service/
├── app/
│   ├── models/           # SQLAlchemy database models
│   ├── services/         # Business logic services
│   ├── routes/           # API route handlers
│   ├── middleware/       # JWT and other middleware
│   ├── events/           # RabbitMQ event publisher
│   ├── schemas.py        # Pydantic validation schemas
│   ├── metrics.py        # Prometheus metrics
│   └── main.py           # FastAPI application entry
├── config/
│   ├── settings.py       # Configuration management
│   ├── database.py       # Database setup
│   └── logging_config.py # Logging configuration
├── tests/
│   ├── unit/             # Unit tests
│   ├── integration/      # Integration tests
│   └── conftest.py       # Pytest fixtures
├── scripts/
│   └── init-db.sql       # Database initialization
├── Dockerfile            # Docker image definition
├── docker-compose.yml    # Multi-container setup
├── requirements.txt      # Python dependencies
├── pytest.ini            # Pytest configuration
└── .env.example          # Environment variables template
```

## Quick Start

### 1. Clone and Setup

```bash
cd transport-service
cp .env.example .env
```

### 2. Docker Compose (Recommended)

```bash
# Start all services
docker-compose up -d

# Check service health
curl http://localhost:8006/health

# View API docs
open http://localhost:8006/api/docs

# RabbitMQ Management UI
open http://localhost:15672  # guest/guest

# pgAdmin
open http://localhost:5050   # admin@example.com/admin
```

### 3. Local Development Setup

```bash
# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt

# Set up environment
cp .env.example .env
# Edit .env with your local database connection

# Run application
python -m uvicorn app.main:app --reload --port 8006

# Run tests
pytest
```

## API Endpoints

### Authentication
All endpoints require JWT token in Authorization header:
```bash
Authorization: Bearer <token>
```

### Transport Management

#### Create Transport
```bash
POST /api/v1/transports
Content-Type: application/json

{
  "tracking_number": "TRK001",
  "origin": "New York",
  "destination": "Los Angeles",
  "status": "pending",
  "estimated_cost": 500.0,
  "distance_km": 2800.0,
  "carrier_name": "CarrierCo"
}
```

#### List Transports
```bash
GET /api/v1/transports?skip=0&limit=100&status=pending&is_active=true
```

#### Get Transport
```bash
GET /api/v1/transports/{transport_id}
```

#### Update Transport
```bash
PUT /api/v1/transports/{transport_id}
Content-Type: application/json

{
  "status": "in_transit",
  "actual_cost": 450.0
}
```

#### Delete Transport
```bash
DELETE /api/v1/transports/{transport_id}
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
  "version": "1.0.0",
  "database": "healthy",
  "rabbitmq": "healthy"
}
```

#### Metrics
```bash
GET /metrics
```

Returns Prometheus metrics in text format.

## Configuration

Edit `.env` file to configure:

```env
# Application
APP_NAME=Transport Microservice
APP_VERSION=1.0.0
DEBUG=false

# Server
HOST=0.0.0.0
PORT=8006

# Database
DATABASE_URL=postgresql://user:password@localhost:5432/transport_db
DATABASE_ECHO=false

# JWT
JWT_SECRET_KEY=your-secret-key-change-this
JWT_ALGORITHM=HS256
JWT_EXPIRATION_HOURS=24

# RabbitMQ
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest

# Logging
LOG_LEVEL=INFO
LOG_FORMAT=json

# Metrics
METRICS_ENABLED=true
```

## JWT Token Generation

Example to generate a JWT token for testing:

```python
import jwt
from datetime import datetime, timedelta

payload = {
    "user_id": 1,
    "company_id": 1,
    "exp": datetime.utcnow() + timedelta(hours=24)
}

token = jwt.encode(
    payload,
    "your-secret-key",
    algorithm="HS256"
)

print(f"Bearer {token}")
```

## Database Models

### Transport
- `id` (Int): Primary key
- `company_id` (Int): Multi-tenancy isolation
- `tracking_number` (String): Unique tracking identifier
- `origin` (String): Departure location
- `destination` (String): Arrival location
- `status` (String): pending, in_transit, delivered, cancelled
- `distance_km` (Float): Distance in kilometers
- `estimated_cost` (Float): Estimated cost
- `actual_cost` (Float): Actual cost
- `carrier_name` (String): Carrier/shipping company
- `vehicle_number` (String): Vehicle identifier
- `driver_name` (String): Driver name
- `driver_contact` (String): Driver contact
- `scheduled_date` (DateTime): Scheduled date
- `actual_departure` (DateTime): Actual departure timestamp
- `actual_arrival` (DateTime): Actual arrival timestamp
- `created_at` (DateTime): Creation timestamp
- `updated_at` (DateTime): Last update timestamp
- `is_active` (Boolean): Soft delete flag

## RabbitMQ Events

Published events:
- `transport.created`: When a transport is created
- `transport.updated`: When a transport is updated
- `transport.status_changed`: When status changes
- `transport.deleted`: When a transport is deleted

Event payload:
```json
{
  "event_type": "created|updated|status_changed|deleted",
  "data": {
    "transport_id": 1,
    "company_id": 1,
    ...additional_data
  }
}
```

## Testing

### Run All Tests
```bash
pytest
```

### Run Specific Test Suite
```bash
pytest tests/unit/
pytest tests/integration/
```

### Run with Coverage Report
```bash
pytest --cov=app --cov=config --cov-report=html
open htmlcov/index.html
```

### Run Specific Test
```bash
pytest tests/unit/test_transport_service.py::TestTransportService::test_create_transport_success -v
```

## Logging

Logs are output in JSON format for better observability:

```json
{
  "timestamp": "2024-01-15T10:30:45.123Z",
  "level": "INFO",
  "message": "Created transport 1 for company 1",
  "app": "Transport Microservice",
  "version": "1.0.0"
}
```

## Metrics

Prometheus metrics available at `/metrics`:

- `http_requests_total`: Total HTTP requests by method, endpoint, status code
- `http_request_duration_seconds`: HTTP request latency histogram
- `transport_operations_total`: Total transport operations by type and status
- `transport_by_status`: Gauge of transports by status
- `transport_total_cost`: Total transport cost by company
- `database_connections`: Active database connections
- `rabbitmq_messages_published`: RabbitMQ messages published
- `rabbitmq_connection_errors`: RabbitMQ connection errors

## Security

- JWT token-based authentication on all API endpoints
- Company-level data isolation
- Passwords hashed in configuration
- Non-root user in Docker container
- CORS enabled (configure for production)

## Performance

- Connection pooling for database
- Async/await throughout for high concurrency
- Efficient queries with indexes
- Lazy loading of modules
- Prometheus metrics for monitoring

## Production Deployment

### Pre-deployment Checklist

- [ ] Set strong `JWT_SECRET_KEY`
- [ ] Use environment-specific `.env`
- [ ] Enable database backups
- [ ] Configure RabbitMQ persistence
- [ ] Set `DEBUG=false`
- [ ] Configure CORS origins
- [ ] Set up log aggregation
- [ ] Configure metrics scraping
- [ ] Set up monitoring/alerting
- [ ] Health check monitoring

### Docker Deployment

```bash
# Build image
docker build -t transport-service:1.0.0 .

# Run container
docker run -d \
  --name transport-api \
  -p 8006:8006 \
  -e DATABASE_URL=postgresql://... \
  -e JWT_SECRET_KEY=... \
  transport-service:1.0.0
```

### Kubernetes (if using K8s)

Create appropriate ConfigMaps, Secrets, Deployments, Services, etc. based on your infrastructure.

## Troubleshooting

### Database Connection Issues
```bash
# Check PostgreSQL
docker-compose logs postgres

# Test connection
psql -h localhost -U transport_user -d transport_db
```

### RabbitMQ Issues
```bash
# Check RabbitMQ
docker-compose logs rabbitmq

# Access management UI
http://localhost:15672
```

### Application Logs
```bash
# View logs
docker-compose logs transport-api

# Follow logs
docker-compose logs -f transport-api
```

## Development

### Adding New Features

1. Create database model in `app/models/`
2. Create Pydantic schema in `app/schemas.py`
3. Create service logic in `app/services/`
4. Create API routes in `app/routes/`
5. Add tests in `tests/unit/` and `tests/integration/`

### Code Style

- Use type hints
- Follow PEP 8
- Document functions
- Write tests

## License

MIT

## Support

For issues, feature requests, or questions, please open an issue in the repository.
