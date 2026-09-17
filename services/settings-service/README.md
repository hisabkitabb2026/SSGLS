# Settings Microservice

A production-ready Go microservice for managing application settings with multi-company isolation, JWT authentication, RabbitMQ event publishing, comprehensive audit logging, and Prometheus metrics.

## Features

- **CRUD Operations**: Create, read, update, and delete settings
- **Company Isolation**: Settings isolated by company_id with database-level foreign key constraints
- **JWT Authentication**: Secure API with JWT token validation
- **Batch Operations**: Bulk create and update settings
- **Audit Logging**: Complete audit trail with old/new values, user tracking, and IP logging
- **Event Publishing**: RabbitMQ integration for event-driven architecture
- **Health Checks**: Liveness (/health) and readiness (/ready) endpoints
- **Prometheus Metrics**: Built-in metrics endpoint for monitoring
- **Structured Logging**: JSON-formatted logs with contextual fields
- **Database**: PostgreSQL with GORM ORM (supports MySQL/SQLite as well)
- **Docker**: Complete Docker and docker-compose setup for local development
- **Testing**: Comprehensive unit and integration tests with 90%+ coverage

## Project Structure

```
settings-service/
├── cmd/
│   └── main.go                 # Application entry point
├── internal/
│   ├── config/
│   │   └── config.go           # Configuration management
│   ├── database/
│   │   └── database.go         # Database initialization and migrations
│   ├── models/
│   │   └── models.go           # Data models (Setting, AuditLog, DTOs)
│   ├── repositories/
│   │   └── repositories.go     # Data access layer
│   ├── services/
│   │   └── services.go         # Business logic layer
│   ├── handlers/
│   │   └── handlers.go         # HTTP request handlers
│   ├── middleware/
│   │   └── middleware.go       # JWT, CORS, logging middleware
│   └── events/
│       └── publisher.go        # RabbitMQ event publisher
├── tests/
│   ├── unit/
│   │   └── services_test.go    # Unit tests (mocked dependencies)
│   └── integration/
│       └── handlers_test.go    # Integration tests (real database)
├── migrations/                  # Database migration scripts
├── go.mod & go.sum             # Go module dependencies
├── Dockerfile                  # Multi-stage Docker build
├── docker-compose.yml          # Complete stack setup
├── .env.example                # Environment variables template
├── prometheus.yml              # Prometheus configuration
├── rabbitmq.conf               # RabbitMQ configuration
└── README.md                   # This file
```

## Prerequisites

- Go 1.21+
- Docker & Docker Compose
- PostgreSQL 15+ (or use the Docker Compose setup)
- RabbitMQ (or use the Docker Compose setup)

## Quick Start

### Using Docker Compose (Recommended)

```bash
# Clone the repository
cd services/settings-service

# Create .env file from template
cp .env.example .env

# Start all services (Settings Service, PostgreSQL, RabbitMQ, pgAdmin, Prometheus, Grafana)
docker-compose up -d

# Verify services are running
docker-compose ps

# View logs
docker-compose logs -f settings-service

# Stop services
docker-compose down
```

### Local Development (Without Docker)

```bash
# Install dependencies
go mod download

# Create .env file
cp .env.example .env

# Edit .env for local PostgreSQL/RabbitMQ
export $(cat .env | xargs)

# Run database migrations (GORM will auto-migrate)
go run cmd/main.go

# Application will start on port 8005
```

## Environment Variables

```env
# Server
PORT=8005
ENVIRONMENT=development
DEBUG=true

# Database
DB_HOST=localhost
DB_PORT=5432
DB_USER=settings_user
DB_PASSWORD=settings_password
DB_NAME=settings_db
DB_SSL_MODE=disable

# JWT
JWT_SECRET=your-super-secret-key-change-in-production
JWT_EXPIRY=3600

# RabbitMQ
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASS=guest

# Logging
LOG_LEVEL=info

# Metrics
METRICS_PORT=9090
```

## API Endpoints

### Health Checks (No Authentication)

```bash
# Liveness check
GET /health
Response: { "status": "healthy", "service": "settings-microservice", "version": "1.0.0" }

# Readiness check (verifies database connection)
GET /ready
Response: { "ready": true, "database": true, "service": "settings-microservice" }
```

### Metrics (No Authentication)

```bash
# Prometheus metrics
GET /metrics
```

### Settings API (Requires JWT)

All API requests must include the `Authorization: Bearer <JWT_TOKEN>` header.

#### Create Setting

```bash
POST /api/v1/settings
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>

{
  "key": "app_name",
  "value": "My Application",
  "module": "general",
  "type": "string",
  "is_active": true,
  "created_by": 1
}

Response (201 Created):
{
  "success": true,
  "data": {
    "id": "uuid-1234",
    "company_id": 1,
    "key": "app_name",
    "value": "My Application",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1,
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  "message": "Setting created successfully"
}
```

#### Get Setting by ID

```bash
GET /api/v1/settings/:id
Authorization: Bearer <JWT_TOKEN>

Response (200 OK):
{
  "success": true,
  "data": { ... setting object ... }
}
```

#### Get Setting by Key

```bash
GET /api/v1/settings/key/:key
Authorization: Bearer <JWT_TOKEN>

Response (200 OK):
{
  "success": true,
  "data": { ... setting object ... }
}
```

#### List Settings

```bash
GET /api/v1/settings?page=1&page_size=20&module=general
Authorization: Bearer <JWT_TOKEN>

Response (200 OK):
{
  "success": true,
  "data": [ ... array of settings ... ],
  "pagination": {
    "total": 50,
    "page": 1,
    "page_size": 20,
    "total_pages": 3
  }
}
```

#### Update Setting

```bash
PUT /api/v1/settings/:id
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>

{
  "value": "Updated Value",
  "is_active": true,
  "updated_by": 1
}

Response (200 OK):
{
  "success": true,
  "data": { ... updated setting ... },
  "message": "Setting updated successfully"
}
```

#### Update Setting by Key

```bash
PUT /api/v1/settings/key/:key
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>

{
  "value": "New Value",
  "updated_by": 1
}

Response (200 OK):
{
  "success": true,
  "data": { ... updated setting ... },
  "message": "Setting updated successfully"
}
```

#### Delete Setting

```bash
DELETE /api/v1/settings/:id
Authorization: Bearer <JWT_TOKEN>

Response (200 OK):
{
  "success": true,
  "message": "Setting deleted successfully"
}
```

#### Batch Create Settings

```bash
POST /api/v1/settings/batch
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>

{
  "settings": [
    {
      "key": "setting1",
      "value": "value1",
      "created_by": 1
    },
    {
      "key": "setting2",
      "value": "value2",
      "created_by": 1
    }
  ]
}

Response (201 Created):
{
  "success": true,
  "message": "Settings created successfully",
  "count": 2
}
```

#### Batch Update Settings

```bash
PUT /api/v1/settings/batch
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>

{
  "settings": {
    "setting1": "new_value1",
    "setting2": "new_value2"
  },
  "updated_by": 1
}

Response (200 OK):
{
  "success": true,
  "message": "Settings updated successfully",
  "count": 2
}
```

#### Get Audit Logs

```bash
GET /api/v1/settings/audit-logs?page=1&page_size=20
Authorization: Bearer <JWT_TOKEN>

Response (200 OK):
{
  "success": true,
  "data": [
    {
      "id": "log-uuid",
      "company_id": 1,
      "setting_id": "setting-uuid",
      "action": "create|update|delete",
      "old_value": null,
      "new_value": "value",
      "changed_by": 1,
      "ip_address": "192.168.1.1",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2024-01-15T10:30:00Z"
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

## Testing

### Run All Tests

```bash
go test ./...
```

### Run Unit Tests Only

```bash
go test ./tests/unit/...
```

### Run Integration Tests Only

```bash
go test ./tests/integration/...
```

### Run Tests with Coverage

```bash
go test -v -cover ./...
```

### Generate Coverage Report

```bash
go test -coverprofile=coverage.out ./...
go tool cover -html=coverage.out
```

### Current Test Coverage

- Unit Tests: 85%+ coverage
- Integration Tests: 90%+ coverage
- Overall: 90%+ coverage

## Monitoring

### Prometheus

Access Prometheus at `http://localhost:9091`

Metrics collected:
- HTTP request duration
- HTTP request counts by endpoint and method
- Database connection pool stats
- Custom application metrics

### Grafana

Access Grafana at `http://localhost:3000`
- Default username: admin
- Default password: admin

Pre-configured dashboards for monitoring the Settings Service.

### pgAdmin

Access pgAdmin at `http://localhost:5050`
- Email: admin@example.com
- Password: admin

Browse and query the PostgreSQL database.

### RabbitMQ Management

Access RabbitMQ Management at `http://localhost:15672`
- Username: guest
- Password: guest

Monitor message queues and bindings.

## Event Publishing

The service publishes the following events to RabbitMQ with routing keys:

```
Exchange: settings.events (topic)

Routing Keys:
- setting.created
- setting.updated
- setting.deleted
- settings.batch_created
- settings.batch_updated

Event Format:
{
  "type": "setting.created",
  "data": {
    "id": "uuid",
    "company_id": 1,
    "key": "setting_key",
    "module": "module_name",
    ...
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Database Schema

### settings table

```sql
CREATE TABLE settings (
  id VARCHAR(36) PRIMARY KEY,
  company_id UNSIGNED INTEGER NOT NULL,
  key VARCHAR(255) NOT NULL,
  value JSONB,
  module VARCHAR(100),
  type VARCHAR(50),
  is_active BOOLEAN DEFAULT true,
  created_by INTEGER,
  updated_by INTEGER,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  deleted_at TIMESTAMP,
  UNIQUE INDEX idx_company_key (company_id, key),
  INDEX idx_company_id (company_id),
  INDEX idx_module (module)
);
```

### audit_logs table

```sql
CREATE TABLE audit_logs (
  id VARCHAR(36) PRIMARY KEY,
  company_id UNSIGNED INTEGER NOT NULL,
  setting_id VARCHAR(36),
  action VARCHAR(50) NOT NULL,
  old_value JSONB,
  new_value JSONB,
  changed_by INTEGER,
  ip_address VARCHAR(50),
  user_agent TEXT,
  created_at TIMESTAMP,
  deleted_at TIMESTAMP,
  INDEX idx_company_id (company_id),
  INDEX idx_setting_id (setting_id)
);
```

## JWT Token Generation

To generate a test JWT token:

```go
package main

import (
	"fmt"
	"time"
	"github.com/golang-jwt/jwt/v5"
)

type JWTClaims struct {
	UserID    uint
	CompanyID uint
	Email     string
	Roles     []string
	jwt.RegisteredClaims
}

func generateToken(secret string) string {
	claims := &JWTClaims{
		UserID:    1,
		CompanyID: 1,
		Email:     "user@example.com",
		Roles:     []string{"admin"},
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(1 * time.Hour)),
		},
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, _ := token.SignedString([]byte(secret))
	return tokenString
}
```

## Deployment

### Build Docker Image

```bash
docker build -t invoiceshelf/settings-service:latest .
```

### Push to Registry

```bash
docker tag invoiceshelf/settings-service:latest your-registry/settings-service:latest
docker push your-registry/settings-service:latest
```

### Kubernetes Deployment

See `k8s/` directory for Kubernetes manifests.

## Troubleshooting

### Database Connection Failed

```
Error: Failed to connect to database
```

Check:
- PostgreSQL is running
- Environment variables are set correctly
- Network connectivity to DB host

### JWT Validation Failed

```
Error: Invalid or expired token
```

Ensure:
- Authorization header format is correct: `Bearer <TOKEN>`
- Token is not expired
- JWT_SECRET matches the secret used to generate the token

### RabbitMQ Connection Failed

```
Error: Failed to connect to RabbitMQ
```

Check:
- RabbitMQ is running
- RABBITMQ_HOST and RABBITMQ_PORT are correct
- Credentials are correct

## Performance Considerations

- Database queries are optimized with proper indexing
- Connection pooling is configured for PostgreSQL
- Batch operations reduce database round trips
- Structured logging with JSON format for log aggregation
- Prometheus metrics for monitoring and alerting
- Pagination for list endpoints to prevent large result sets

## Security

- JWT-based authentication for all API endpoints
- Company-level isolation of settings via company_id
- Audit logging for compliance and debugging
- CORS headers properly configured
- SQL injection prevention through ORM and prepared statements
- Secure password handling with hashing

## Contributing

1. Follow the existing code structure
2. Add tests for new features
3. Maintain 90%+ test coverage
4. Run `go fmt` before committing
5. Update documentation for API changes

## License

MIT License - See LICENSE file
