# Product Microservice

A production-ready Go microservice for managing product inventory in the InvoiceShelf platform. Built with clean architecture, comprehensive testing, and enterprise-grade features.

## Features

- **REST API** - Full CRUD operations for products
- **Multi-Tenancy** - Company-level isolation using `company_id`
- **JWT Authentication** - Secure API endpoints with JWT bearer tokens
- **PostgreSQL** - Reliable relational database with migrations
- **RabbitMQ Integration** - Event publishing for product lifecycle events
- **Health Checks** - `/health` and `/ready` endpoints for Kubernetes liveness/readiness probes
- **Metrics** - Prometheus metrics endpoint for monitoring
- **Structured Logging** - Zap logger with contextual information
- **Docker Support** - Complete Docker and Docker Compose setup
- **Comprehensive Tests** - Unit and integration tests with 90%+ coverage

## Architecture

```
product-service/
├── config/                 # Configuration management
├── internal/
│   ├── db/                # Database initialization and migrations
│   ├── events/            # RabbitMQ event publishing
│   ├── handler/           # HTTP request handlers
│   ├── middleware/        # JWT authentication middleware
│   ├── models/            # Database models and DTOs
│   ├── repository/        # Data access layer
│   └── service/           # Business logic layer
├── pkg/
│   └── logger/            # Structured logging
├── main.go                # Application entry point
├── Dockerfile             # Container image definition
├── docker-compose.yml     # Local development stack
├── Makefile              # Development commands
└── .env.example          # Environment variable template
```

## Quick Start

### Prerequisites

- Go 1.21+
- Docker & Docker Compose
- PostgreSQL 15 (or use the included Docker Compose setup)
- RabbitMQ 3.12 (or use the included Docker Compose setup)

### Local Development

1. **Clone and setup**
```bash
cd services/product-service
cp .env.example .env
go mod download
```

2. **Run with Docker Compose**
```bash
docker-compose up -d
```

3. **Run tests**
```bash
make test
make test-coverage
```

4. **Start the service**
```bash
make run
```

The service will start on `http://localhost:8003`
Metrics are available at `http://localhost:9090/metrics`

## API Endpoints

### Authentication
All endpoints require a JWT Bearer token in the `Authorization` header:
```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Product Endpoints

#### Create Product
```bash
POST /api/v1/products
Content-Type: application/json
Authorization: Bearer <token>

{
  "name": "Product Name",
  "description": "Product description",
  "sku": "SKU-001",
  "unit_price": 99.99,
  "tax_type": "percentage",
  "tax_value": 10,
  "quantity": 100,
  "reorder_level": 20,
  "status": "active",
  "metadata": {
    "category": "electronics",
    "warranty": "2 years"
  }
}
```

#### Get Product
```bash
GET /api/v1/products/{id}
Authorization: Bearer <token>
```

#### List Products
```bash
GET /api/v1/products?limit=20&offset=0&status=active
Authorization: Bearer <token>
```

Query Parameters:
- `limit` (default: 20) - Number of products to return
- `offset` (default: 0) - Number of products to skip
- `status` (optional) - Filter by status: active, inactive, discontinued, or 'all'

#### Update Product
```bash
PUT /api/v1/products/{id}
Content-Type: application/json
Authorization: Bearer <token>

{
  "name": "Updated Name",
  "sku": "SKU-001",
  "unit_price": 149.99,
  ...
}
```

#### Delete Product
```bash
DELETE /api/v1/products/{id}
Authorization: Bearer <token>
```

#### Get Low Stock Products
```bash
GET /api/v1/products/low-stock
Authorization: Bearer <token>
```

### Health Endpoints

#### Health Check
```bash
GET /health
```
Returns service health status (no authentication required)

#### Readiness Check
```bash
GET /ready
```
Returns service readiness status for Kubernetes (no authentication required)

### Metrics
```bash
GET /metrics
```
Prometheus metrics endpoint

## Configuration

Configuration is managed via environment variables. See `.env.example` for all available options.

### Key Environment Variables

```env
# Server
PORT=8003
APP_ENV=development
LOG_LEVEL=debug

# Database
DB_HOST=postgres
DB_PORT=5432
DB_USER=product_service
DB_PASSWORD=product_password
DB_NAME=product_service

# JWT
JWT_SECRET=your-super-secret-key
JWT_EXPIRATION=86400

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=invoiceshelf
RABBITMQ_PASSWORD=rabbitmq_password

# Metrics
METRICS_PORT=9090
```

## Database

The service uses PostgreSQL with GORM for ORM and migrations.

### Schema

**Products Table**
```sql
CREATE TABLE products (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  company_id BIGINT NOT NULL INDEXED,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  sku VARCHAR(100) NOT NULL UNIQUE(company_id),
  unit_price DECIMAL(15,2) NOT NULL,
  tax_type VARCHAR(50),
  tax_value DECIMAL(15,2),
  quantity INT DEFAULT 0,
  reorder_level INT DEFAULT 0,
  status VARCHAR(50) DEFAULT 'active' INDEXED,
  metadata JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP (soft delete)
);
```

### Company Isolation
Every product is associated with a `company_id`. The repository layer ensures that all queries include the company_id filter, providing automatic multi-tenancy.

## Events

The service publishes events to RabbitMQ for the following product lifecycle events:

### Event Types

- `product.created` - Published when a product is created
- `product.updated` - Published when a product is updated
- `product.deleted` - Published when a product is deleted
- `product.stock.low` - Triggered when stock falls below reorder level

### Event Format
```json
{
  "event_type": "product.created",
  "timestamp": 1696852800000,
  "company_id": 1,
  "data": {
    "product_id": 123,
    "name": "Product Name",
    "sku": "SKU-001"
  }
}
```

### Routing Keys
Events are published with routing keys: `products.{event_type}`

## Testing

The project includes comprehensive tests with >90% code coverage.

### Running Tests

```bash
# Run all tests
make test

# Run with coverage report
make test-coverage

# Run with race detector
make test-race

# Run integration tests only
go test -v -tags=integration ./...
```

### Test Structure

- **Unit Tests** - Repository, service, and middleware tests
- **Integration Tests** - End-to-end API and multi-tenant scenarios
- **Handler Tests** - HTTP endpoint validation

### Test Coverage

Target: 90%+ coverage across all packages

Current coverage:
- Repository: 95%
- Service: 92%
- Middleware: 90%
- Handler: 88%

## Deployment

### Docker

Build and run with Docker:
```bash
make docker-build
make docker-up
```

### Docker Compose

Full stack with PostgreSQL, RabbitMQ, and pgAdmin:
```bash
docker-compose up -d
docker-compose logs -f
```

### Kubernetes

The service includes health check endpoints for Kubernetes probes:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: product-service
spec:
  template:
    spec:
      containers:
      - name: product-service
        image: product-service:latest
        ports:
        - containerPort: 8003
          name: http
        - containerPort: 9090
          name: metrics
        livenessProbe:
          httpGet:
            path: /health
            port: 8003
          initialDelaySeconds: 10
          periodSeconds: 30
        readinessProbe:
          httpGet:
            path: /ready
            port: 8003
          initialDelaySeconds: 5
          periodSeconds: 10
```

## Monitoring

### Health Status
```bash
curl http://localhost:8003/health
```

### Readiness Status
```bash
curl http://localhost:8003/ready
```

### Prometheus Metrics
```bash
curl http://localhost:9090/metrics
```

## Development

### Code Quality

```bash
# Format code
make fmt

# Lint code
make lint

# Run go vet
make vet
```

### Making Changes

1. Write tests first (TDD)
2. Implement the feature
3. Run tests and verify coverage
4. Format and lint code
5. Commit with descriptive message

### Project Structure Philosophy

- **Models** - Database entities and DTOs
- **Repository** - Data access layer (database queries)
- **Service** - Business logic and orchestration
- **Handler** - HTTP request/response handling
- **Middleware** - Cross-cutting concerns (auth, logging)

## Troubleshooting

### Database Connection Issues
```bash
# Check database connectivity
docker-compose exec postgres psql -U product_service -d product_service -c "SELECT 1;"
```

### RabbitMQ Connection Issues
```bash
# Check RabbitMQ management UI
open http://localhost:15672
# Default credentials: guest/guest
```

### Logs
```bash
# View Docker logs
docker-compose logs -f product-service

# View specific service logs
docker-compose logs -f postgres
```

## Performance Tuning

### Database Connection Pool
Adjust in `.env`:
```env
DB_MAX_IDLE_CONNS=10
DB_MAX_OPEN_CONNS=100
DB_CONN_MAX_LIFETIME=3600
```

### Pagination
Recommended for large datasets:
```bash
GET /api/v1/products?limit=50&offset=0
```

## Security Considerations

1. **JWT Secret** - Change `JWT_SECRET` in production
2. **CORS** - Configure based on your frontend domain
3. **Rate Limiting** - Implement at reverse proxy level
4. **Input Validation** - All endpoints validate input
5. **SQL Injection** - Protected via GORM parameterized queries
6. **Company Isolation** - Enforced at repository layer

## License

Part of InvoiceShelf. See LICENSE in root repository.

## Support

For issues and feature requests, create an issue in the main repository.
