# Settings Service Architecture

## Overview

The Settings Microservice is a production-ready Go application following a clean, layered architecture with clear separation of concerns. It implements multi-company tenancy with complete isolation and provides comprehensive audit logging.

## Architecture Layers

```
┌─────────────────────────────────────────┐
│         API Layer (REST/HTTP)           │
│        handlers/handlers.go             │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Business Logic Layer              │
│        services/services.go             │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│      Data Access Layer (DAL)            │
│    repositories/repositories.go         │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│         Database Layer                  │
│    GORM + PostgreSQL/MySQL/SQLite      │
└─────────────────────────────────────────┘
```

## Component Overview

### 1. Entry Point (`cmd/main.go`)
- Application initialization
- Configuration loading
- Dependency injection
- HTTP server setup
- Graceful shutdown handling

### 2. Configuration (`internal/config/config.go`)
- Environment variable loading via `.env`
- Configuration validation
- Defaults for development/production

### 3. Database Layer (`internal/database/database.go`)
- GORM initialization
- Database connection pooling
- Auto-migration setup
- Support for PostgreSQL, MySQL, SQLite

### 4. Models (`internal/models/models.go`)
- **Setting**: Core entity for storing key-value pairs with company isolation
- **AuditLog**: Audit trail for compliance and debugging
- **DTOs**: Request/Response objects for API contracts

### 5. Repository Layer (`internal/repositories/repositories.go`)
- **SettingRepository**: CRUD operations for settings
- **AuditLogRepository**: Audit trail management
- Database query abstraction
- Error handling and logging

### 6. Service Layer (`internal/services/services.go`)
- **SettingService**: Business logic orchestration
- Event publishing coordination
- Audit log creation
- Validation and transformation
- Batch operation handling

### 7. Handler Layer (`internal/handlers/handlers.go`)
- **SettingHandler**: HTTP request/response handling
- **HealthHandler**: Health check endpoints
- Request validation
- Response formatting
- Error handling

### 8. Middleware (`internal/middleware/middleware.go`)
- **JWTMiddleware**: Token validation and extraction
- **CORSMiddleware**: Cross-origin resource sharing
- **LoggingMiddleware**: Request/response logging
- **RecoveryMiddleware**: Panic recovery

### 9. Events (`internal/events/publisher.go`)
- **Publisher**: RabbitMQ event publishing
- Event serialization
- Connection management
- Error handling

## Data Flow

### Create Setting Request Flow

```
HTTP Request (POST /api/v1/settings)
    ↓
[JWTMiddleware] - Validate token, extract company_id
    ↓
[Handler.Create] - Bind JSON, validate input
    ↓
[Service.Create] - Business logic, marshal value
    ↓
[Repository.Create] - Insert into database
    ↓
[AuditLog.Create] - Log the action
    ↓
[EventPublisher.Publish] - Publish setting.created event
    ↓
HTTP Response (201 Created with Setting object)
```

### Get Setting Request Flow

```
HTTP Request (GET /api/v1/settings/:id)
    ↓
[JWTMiddleware] - Validate token, extract company_id
    ↓
[Handler.Get] - Extract ID from URL
    ↓
[Service.Get] - Retrieve setting
    ↓
[Repository.GetByID] - Query database (company_id + id)
    ↓
HTTP Response (200 OK with Setting object)
```

## Database Schema

### settings table

```
┌──────────────┬──────────────┬─────────────────┐
│   Column     │     Type     │   Constraints   │
├──────────────┼──────────────┼─────────────────┤
│ id (PK)      │ VARCHAR(36)  │ NOT NULL        │
│ company_id   │ INTEGER      │ NOT NULL, INDEX │
│ key          │ VARCHAR(255) │ NOT NULL, INDEX │
│ value        │ JSONB        │                 │
│ module       │ VARCHAR(100) │ INDEX           │
│ type         │ VARCHAR(50)  │                 │
│ is_active    │ BOOLEAN      │ DEFAULT true    │
│ created_by   │ INTEGER      │                 │
│ updated_by   │ INTEGER      │                 │
│ created_at   │ TIMESTAMP    │                 │
│ updated_at   │ TIMESTAMP    │                 │
│ deleted_at   │ TIMESTAMP    │ INDEX (soft del)│
├──────────────┴──────────────┴─────────────────┤
│ UNIQUE: (company_id, key)                    │
│ Soft delete via deleted_at                   │
└────────────────────────────────────────────────┘
```

### audit_logs table

```
┌──────────────┬──────────────┬─────────────────┐
│   Column     │     Type     │   Constraints   │
├──────────────┼──────────────┼─────────────────┤
│ id (PK)      │ VARCHAR(36)  │ NOT NULL        │
│ company_id   │ INTEGER      │ NOT NULL, INDEX │
│ setting_id   │ VARCHAR(36)  │ INDEX           │
│ action       │ VARCHAR(50)  │ INDEX           │
│ old_value    │ JSONB        │                 │
│ new_value    │ JSONB        │                 │
│ changed_by   │ INTEGER      │ INDEX           │
│ ip_address   │ VARCHAR(50)  │                 │
│ user_agent   │ TEXT         │                 │
│ created_at   │ TIMESTAMP    │ INDEX           │
│ deleted_at   │ TIMESTAMP    │ INDEX (soft del)│
└──────────────┴──────────────┴─────────────────┘
```

## Company Isolation Strategy

Multi-tenancy is achieved through:

1. **Database Level**: Every table has `company_id` column
2. **JWT Claims**: `company_id` extracted from JWT token
3. **Middleware**: Company ID injected into Gin context
4. **Queries**: All queries filtered by company_id
5. **Audit Logs**: Company ID tracked for compliance

```go
// Pattern used in all queries
query := r.db.Where("company_id = ?", companyID)
```

## API Design

### Endpoint Structure

```
GET    /health                          # Health check (no auth)
GET    /ready                           # Readiness check (no auth)
GET    /metrics                         # Prometheus metrics (no auth)

POST   /api/v1/settings                 # Create
GET    /api/v1/settings                 # List (paginated)
GET    /api/v1/settings/:id             # Get by ID
PUT    /api/v1/settings/:id             # Update
DELETE /api/v1/settings/:id             # Delete

GET    /api/v1/settings/key/:key        # Get by key
PUT    /api/v1/settings/key/:key        # Update by key

POST   /api/v1/settings/batch           # Batch create
PUT    /api/v1/settings/batch           # Batch update

GET    /api/v1/settings/audit-logs      # Get audit logs
```

### Request/Response Pattern

**Request:**
- JWT token in `Authorization: Bearer <token>` header
- JSON body for POST/PUT
- Query parameters for filtering/pagination

**Response (Success):**
```json
{
  "success": true,
  "data": { /* resource */ },
  "message": "Optional message",
  "pagination": { /* optional */ }
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "Error message",
  "status_code": 400,
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Event Publishing

### RabbitMQ Integration

```
Exchange: settings.events (type: topic)
    │
    ├─ setting.created
    ├─ setting.updated
    ├─ setting.deleted
    ├─ settings.batch_created
    └─ settings.batch_updated

Event Format:
{
  "type": "setting.created",
  "data": { /* setting data */ },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

## Security Architecture

### JWT Authentication

```
Token Format: Header.Payload.Signature

Payload Claims:
{
  "user_id": 1,
  "company_id": 1,
  "email": "user@example.com",
  "roles": ["admin"],
  "exp": 1234567890
}

Validation:
1. Check token signature
2. Verify expiration
3. Extract company_id for isolation
4. Verify required claims
```

### CORS Policy

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

## Monitoring & Metrics

### Prometheus Metrics

```
http_requests_total
http_request_duration_seconds
http_request_size_bytes
http_response_size_bytes
db_query_duration_seconds
db_connection_pool_size
rabbitmq_publish_total
rabbitmq_publish_errors
```

### Logging

- **Format**: JSON for easy parsing
- **Levels**: DEBUG, INFO, WARN, ERROR
- **Context**: Includes request ID, company_id, user_id, duration
- **Aggregation**: Compatible with ELK, Datadog, CloudWatch

## Testing Strategy

### Unit Tests (services_test.go)

```
✓ Create setting
✓ Get setting by ID
✓ Get setting by key
✓ List settings
✓ Update setting
✓ Delete setting
✓ Batch operations
✓ Error cases
✓ Audit log creation
```

Coverage: 85%+

### Integration Tests (handlers_test.go)

```
✓ Create via HTTP
✓ Get via HTTP
✓ List via HTTP
✓ Update via HTTP
✓ Delete via HTTP
✓ Authentication
✓ Authorization (company isolation)
✓ Error responses
✓ Health checks
```

Coverage: 90%+

## Performance Considerations

### Database Optimization

- **Indexes**: Company_id, key, module, action for fast queries
- **Connection Pooling**: Configurable min/max connections
- **Pagination**: Limits memory usage for large result sets
- **Soft Deletes**: Preserved data for audit trails

### API Optimization

- **Batch Operations**: Reduce round trips
- **Caching**: Can be added via Redis
- **Compression**: Gin supports gzip
- **Rate Limiting**: Implement at gateway level

### Scalability

- **Horizontal Scaling**: Stateless design
- **Load Balancing**: No session affinity required
- **Database**: Connection pooling for multiple instances
- **Message Queue**: RabbitMQ for async event processing

## Deployment Architecture

### Local Development

```
Developer Machine
    ├─ Go application
    ├─ PostgreSQL (container or local)
    └─ RabbitMQ (container or local)
```

### Docker Deployment

```
Host Machine
    └─ Docker
        ├─ settings-service container
        ├─ PostgreSQL container
        ├─ RabbitMQ container
        ├─ pgAdmin container
        ├─ Prometheus container
        └─ Grafana container
```

### Kubernetes Deployment

```
Kubernetes Cluster
    ├─ Deployment: settings-service (replicas: 3)
    ├─ Service: settings-service (ClusterIP)
    ├─ ConfigMap: settings-config
    ├─ Secrets: settings-jwt, settings-db, settings-rabbitmq
    ├─ HPA: Auto-scaling based on CPU/Memory
    ├─ Ingress: External access with TLS
    └─ ServiceAccount: RBAC permissions
```

## Error Handling

### Hierarchy

```
Repository Errors
    ↓
Service Errors
    ↓
Handler Errors
    ↓
HTTP Response
```

### Error Codes

- **400**: Validation failure
- **401**: Authentication failure
- **403**: Authorization failure (company isolation)
- **404**: Resource not found
- **409**: Conflict (duplicate key)
- **500**: Server error
- **503**: Service unavailable

## Development Workflow

```
1. Write tests (Test-Driven Development)
2. Implement feature
3. Verify tests pass
4. Check coverage (target: 90%+)
5. Format code (go fmt)
6. Lint code (go vet)
7. Commit with meaningful message
8. CI/CD pipeline runs tests
9. Merge to main
10. Deploy to production
```

## Future Enhancements

- [ ] Redis caching layer
- [ ] GraphQL API endpoint
- [ ] Webhook support for events
- [ ] Settings versioning/history
- [ ] Settings encryption at rest
- [ ] Role-based access control (RBAC)
- [ ] Settings validation schemas
- [ ] Import/Export functionality
- [ ] Settings backup/restore
- [ ] Multi-language support for descriptions

## References

- Go Best Practices: https://golang.org/doc/effective_go
- GORM Documentation: https://gorm.io/docs
- JWT RFC: https://tools.ietf.org/html/rfc7519
- RabbitMQ Tutorials: https://www.rabbitmq.com/getstarted.html
- Prometheus Metrics: https://prometheus.io/docs/practices/instrumentation/
