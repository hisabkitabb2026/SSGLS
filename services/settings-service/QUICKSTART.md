# Settings Microservice - Quick Start Guide

## 30-Minute Setup

### Step 1: Start the Stack (2 minutes)

```bash
cd services/settings-service
cp .env.example .env
docker-compose up -d
```

**Services running:**
- Settings API: http://localhost:8005
- PostgreSQL: localhost:5432
- pgAdmin: http://localhost:5050
- RabbitMQ: http://localhost:15672
- Prometheus: http://localhost:9091
- Grafana: http://localhost:3000

### Step 2: Verify Health (1 minute)

```bash
# Check if service is healthy
curl http://localhost:8005/health

# Check readiness (database connection)
curl http://localhost:8005/ready
```

### Step 3: Get JWT Token (5 minutes)

```bash
# Generate a test JWT token (valid for 1 hour)
cat > /tmp/generate_token.go << 'EOF'
package main

import (
	"fmt"
	"time"
	"github.com/golang-jwt/jwt/v5"
)

type Claims struct {
	UserID    uint     `json:"user_id"`
	CompanyID uint     `json:"company_id"`
	Email     string   `json:"email"`
	Roles     []string `json:"roles"`
	jwt.RegisteredClaims
}

func main() {
	claims := &Claims{
		UserID:    1,
		CompanyID: 1,
		Email:     "test@example.com",
		Roles:     []string{"admin"},
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(1 * time.Hour)),
		},
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	tokenString, _ := token.SignedString([]byte("your-secret-key-change-in-production"))
	fmt.Println(tokenString)
}
EOF

go run /tmp/generate_token.go
# Copy the token output and set as environment variable
export JWT_TOKEN="<paste-token-here>"
```

### Step 4: Create a Setting (2 minutes)

```bash
curl -X POST http://localhost:8005/api/v1/settings \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "app_name",
    "value": "My Application",
    "module": "general",
    "type": "string",
    "is_active": true,
    "created_by": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-here",
    "company_id": 1,
    "key": "app_name",
    "value": "My Application",
    ...
  },
  "message": "Setting created successfully"
}
```

Save the `id` from the response.

### Step 5: Get the Setting (1 minute)

```bash
export SETTING_ID="<id-from-previous-response>"

curl -X GET http://localhost:8005/api/v1/settings/$SETTING_ID \
  -H "Authorization: Bearer $JWT_TOKEN"
```

### Step 6: List Settings (1 minute)

```bash
curl -X GET "http://localhost:8005/api/v1/settings?page=1&page_size=20" \
  -H "Authorization: Bearer $JWT_TOKEN" | jq '.'
```

### Step 7: Update Setting (2 minutes)

```bash
curl -X PUT http://localhost:8005/api/v1/settings/$SETTING_ID \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "value": "Updated Application Name",
    "updated_by": 1
  }'
```

### Step 8: View Audit Logs (2 minutes)

```bash
curl -X GET "http://localhost:8005/api/v1/settings/audit-logs?page=1&page_size=20" \
  -H "Authorization: Bearer $JWT_TOKEN" | jq '.data[] | {action, old_value, new_value}'
```

### Step 9: Delete Setting (1 minute)

```bash
curl -X DELETE http://localhost:8005/api/v1/settings/$SETTING_ID \
  -H "Authorization: Bearer $JWT_TOKEN"
```

## Testing

```bash
# Run all tests
make test

# Run specific test type
make test-unit
make test-integration

# Generate coverage report
make coverage
open coverage.html
```

## Monitoring

### View Logs

```bash
# Container logs
docker-compose logs -f settings-service

# Follow specific timestamp
docker-compose logs -f --since 5m settings-service
```

### Check Database

1. Open pgAdmin: http://localhost:5050
2. Login: admin@example.com / admin
3. Connect to PostgreSQL:
   - Host: postgres
   - Username: settings_user
   - Password: settings_password
   - Database: settings_db

### RabbitMQ Management

1. Open http://localhost:15672
2. Login: guest / guest
3. Navigate to "Exchanges"
4. Search for "settings.events"
5. View published events

### Prometheus Metrics

1. Open http://localhost:9091
2. Go to "Graph"
3. Query examples:
   - `http_requests_total{endpoint="/api/v1/settings"}`
   - `http_request_duration_seconds_bucket`
   - `rate(http_requests_total[5m])`

## Common Commands

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View all logs
docker-compose logs -f

# Restart a specific service
docker-compose restart settings-service

# Remove volumes (clean slate)
docker-compose down -v

# Rebuild the image
docker build -t invoiceshelf/settings-service:latest .

# Run locally (without Docker)
go run cmd/main.go

# Format code
make fmt

# Lint code
make lint

# Build binary
make build

# Clean build artifacts
make clean
```

## Troubleshooting

### Service won't start

```bash
# Check logs
docker-compose logs settings-service

# Common issues:
# 1. Port 8005 already in use
lsof -i :8005
# 2. Database not running
docker-compose ps
# 3. Environment variables not set
cat .env
```

### Database connection error

```bash
# Test database connectivity
docker-compose exec postgres psql -U settings_user -d settings_db -c "SELECT 1"

# Check database logs
docker-compose logs postgres
```

### RabbitMQ connection error

```bash
# Check RabbitMQ status
docker-compose exec rabbitmq rabbitmq-diagnostics -q ping

# View RabbitMQ logs
docker-compose logs rabbitmq
```

### JWT token invalid

```bash
# Regenerate token
export JWT_SECRET="your-secret-key-change-in-production"
# Use the token generation script from Step 3
```

## Next Steps

1. **Read the Full Documentation:**
   - `README.md` - Complete feature overview
   - `API.md` - Detailed API documentation
   - `ARCHITECTURE.md` - System design and patterns
   - `DEPLOYMENT.md` - Production deployment guide

2. **Explore the Code:**
   - `cmd/main.go` - Application entry point
   - `internal/handlers/` - HTTP request handling
   - `internal/services/` - Business logic
   - `internal/repositories/` - Data access

3. **Customize:**
   - Update `JWT_SECRET` in `.env`
   - Add custom validation in handlers
   - Extend models with new fields
   - Add custom middleware

4. **Deploy:**
   - Docker: Follow `DEPLOYMENT.md` for container deployment
   - Kubernetes: Use the K8s manifests in `DEPLOYMENT.md`
   - Cloud: AWS ECS, Google Cloud Run, Azure Container Instances

## Performance Benchmarks

Run load tests:

```bash
# Install Apache Bench (macOS)
brew install httpd

# Create 100 settings
ab -n 100 -c 10 \
  -H "Authorization: Bearer $JWT_TOKEN" \
  http://localhost:8005/api/v1/settings/

# View metrics
curl http://localhost:9091/api/v1/query?query=rate(http_requests_total[5m])
```

## Support

- **Documentation**: See README.md, API.md, ARCHITECTURE.md, DEPLOYMENT.md
- **Issues**: Check DEPLOYMENT.md Troubleshooting section
- **Code Examples**: See API.md Examples section
- **Local Testing**: Use Postman collection or curl commands above

## Cleanup

```bash
# Stop all services and remove volumes
docker-compose down -v

# Remove Docker image
docker rmi invoiceshelf/settings-service:latest

# Clean Go build cache
go clean -cache
```

Enjoy building with the Settings Microservice! 🚀
