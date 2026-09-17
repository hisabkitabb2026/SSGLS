# Kong API Gateway Setup for InvoiceShelf Microservices

This document provides complete instructions for setting up and managing Kong API Gateway with InvoiceShelf's 6 DDD microservices architecture.

## Architecture Overview

### Microservices (6 DDD Domains)

1. **Invoice Service** (Port 8001)
   - Manages invoices, line items, and invoice operations
   - Routes: `/api/v1/invoices/*`

2. **Expense Service** (Port 8002)
   - Manages expenses and expense categories
   - Routes: `/api/v1/expenses/*`

3. **Product Service** (Port 8003)
   - Manages product catalog, SKUs, and inventory
   - Routes: `/api/v1/products/*`

4. **Customer Service** (Port 8004)
   - Manages customer information and portals
   - Routes: `/api/v1/customers/*`, `/portal/*`

5. **Settings Service** (Port 8005)
   - Manages configuration, feature flags, and API keys
   - Routes: `/api/v1/settings/*`, `/api/v1/admin/settings/*`

6. **Transport Service** (Port 8006)
   - Manages shipments and delivery tracking
   - Routes: `/api/v1/transport/*`

### API Gateway Stack

- **Kong 3.4** - API Gateway
- **PostgreSQL 15** - Kong database
- **Redis 7** - Rate limiting and caching
- **Prometheus** - Metrics collection
- **Grafana** - Metrics visualization
- **Jaeger** - Distributed tracing
- **Elasticsearch + Kibana** - Log aggregation
- **Konga** - Kong Admin GUI

## Prerequisites

- Docker & Docker Compose (v20.10+)
- curl or similar HTTP client
- 16GB RAM (recommended)
- 20GB disk space

## Quick Start

### 1. Start the Kong Gateway Stack

```bash
# Start Kong and all microservices
./scripts/start-kong-gateway.sh start

# Or using docker-compose directly
docker-compose -f docker-compose.yml \
               -f docker-compose.kong.yml \
               -f docker-compose.microservices.yml up -d
```

### 2. Verify Setup

```bash
# Check service status
./scripts/start-kong-gateway.sh status

# Run health checks
./scripts/start-kong-gateway.sh health

# View system info
./scripts/start-kong-gateway.sh info
```

### 3. Access the Dashboard

```bash
# Open dashboards in browser
./scripts/start-kong-gateway.sh dashboard
```

Or manually visit:
- Kong Admin GUI: http://localhost:8002
- Konga Dashboard: http://localhost:1337
- Grafana: http://localhost:3000 (admin/admin_password)
- Prometheus: http://localhost:9090
- Kibana: http://localhost:5601
- Jaeger: http://localhost:16686

## Configuration Files

### Main Configuration Files

| File | Purpose |
|------|---------|
| `kong.yml` | Declarative configuration for all services, routes, and plugins |
| `docker-compose.kong.yml` | Kong infrastructure containers |
| `docker-compose.microservices.yml` | 6 microservice containers |
| `scripts/kong-startup.sh` | Initialization and configuration loader |
| `scripts/start-kong-gateway.sh` | Complete management script |

### Supporting Configuration Files

| File | Purpose |
|------|---------|
| `config/fluent-bit.conf` | Log aggregation configuration |
| `scripts/init-databases.sql` | Database initialization for microservices |

## Plugins Configuration

### Enabled Plugins

#### Global Plugins (All Services)
- **CORS** - Cross-Origin Resource Sharing
- **Request Transformer** - Add trace IDs and headers
- **Response Transformer** - Add custom response headers
- **Bot Detection** - Detect and block bots
- **Prometheus** - Metrics collection

#### Service-Specific Plugins
- **JWT Authentication** - Token-based authentication
- **Rate Limiting** - 100 requests/minute per consumer
- **Proxy Caching** - 5-minute cache for GET requests
- **Request Size Limiting** - 100MB max payload
- **HTTP Logging** - Send logs to aggregator

#### Advanced Plugins
- **IP Restriction** - Whitelist IPs for admin services
- **ACL** - Role-based access control
- **OAuth2** - Social login support
- **StatsD** - Metrics export
- **Data Dog** - APM integration (optional)

## API Request Flow

```
Client Request
    ↓
Kong Proxy (8000) - Routes incoming requests
    ↓
Global Middleware
  - CORS validation
  - Request transformation (add trace ID)
  - Bot detection
  - Rate limiting check
    ↓
Service-Specific Authentication
  - JWT validation
  - API Key validation
    ↓
Upstream Microservice
  - Invoice (8001)
  - Expense (8002)
  - Product (8003)
  - Customer (8004)
  - Settings (8005)
  - Transport (8006)
    ↓
Response Processing
  - Response transformation
  - Caching (if applicable)
  - Logging
    ↓
Client Response
```

## Managing Services

### Start Services

```bash
# Start all services
./scripts/start-kong-gateway.sh start

# Or specific services
docker-compose -f docker-compose.kong.yml up -d kong
```

### Stop Services

```bash
# Stop all services
./scripts/start-kong-gateway.sh stop

# Or specific services
docker-compose -f docker-compose.kong.yml down
```

### View Logs

```bash
# View logs for specific service
./scripts/start-kong-gateway.sh logs kong
./scripts/start-kong-gateway.sh logs invoice-service
./scripts/start-kong-gateway.sh logs kong-db

# View all logs
./scripts/start-kong-gateway.sh logs
```

### Scale a Service

```bash
# Scale invoice service to 3 replicas
./scripts/start-kong-gateway.sh scale invoice-service 3

# Scale expense service to 2 replicas
./scripts/start-kong-gateway.sh scale expense-service 2
```

### Reload Configuration

```bash
# Reload Kong configuration from kong.yml
./scripts/start-kong-gateway.sh load-config
```

## API Endpoints

### Health Check

```bash
curl -X GET http://localhost:8000/health
```

### Invoice Service Examples

```bash
# List invoices
curl -X GET http://localhost:8000/api/v1/invoices

# Get specific invoice
curl -X GET http://localhost:8000/api/v1/invoices/1

# Create invoice
curl -X POST http://localhost:8000/api/v1/invoices \
  -H "Content-Type: application/json" \
  -d '{"customer_id": 1, "amount": 1000}'

# Get invoice PDF
curl -X GET http://localhost:8000/api/v1/invoices/1/pdf
```

### Other Endpoints

```bash
# Expenses
curl -X GET http://localhost:8000/api/v1/expenses

# Products
curl -X GET http://localhost:8000/api/v1/products

# Customers
curl -X GET http://localhost:8000/api/v1/customers

# Settings
curl -X GET http://localhost:8000/api/v1/settings

# Transport/Shipments
curl -X GET http://localhost:8000/api/v1/transport
```

## Authentication

### JWT Token

1. Obtain a JWT token from the auth service
2. Include in request headers:

```bash
curl -X GET http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### API Key

1. Obtain an API key from settings service
2. Include in request:

```bash
curl -X GET http://localhost:8000/api/v1/invoices \
  -H "X-API-Key: your-api-key"
```

## Monitoring & Observability

### Prometheus Metrics

Metrics available at: http://localhost:9090/metrics

Key metrics:
- `kong_http_requests_total` - Total requests
- `kong_request_latency_ms` - Request latency
- `kong_upstream_latency_ms` - Upstream service latency
- `kong_cache_hit_ratio` - Cache hit rate

### Grafana Dashboards

Dashboards available at: http://localhost:3000

Pre-built dashboards:
- Kong Gateway Performance
- Microservices Latency
- Request Distribution
- Error Rates

### Distributed Tracing

Jaeger available at: http://localhost:16686

Features:
- Trace ID injection via `X-Trace-ID` header
- End-to-end request tracing
- Service dependency visualization
- Performance bottleneck identification

### Log Aggregation

Kibana available at: http://localhost:5601

Logs indexed by:
- `kong-logs-*` - API Gateway logs
- `microservices-logs-*` - Service logs
- Source service
- Request ID / Trace ID

## Database Management

### Access Databases

```bash
# Connect to MySQL
docker exec -it invoiceshelf-mysql-services mysql -u app -p

# Databases
- invoiceshelf_invoice
- invoiceshelf_expense
- invoiceshelf_product
- invoiceshelf_customer
- invoiceshelf_settings
- invoiceshelf_transport
```

### Database Initialization

Databases are automatically initialized on first run with:
- Service-specific schemas
- Basic table structures
- User accounts with appropriate permissions

### Analytics Access

Read-only user for reports:
- Username: `analytics`
- Password: `analytics_password`

## Advanced Configuration

### Custom Rate Limits

Edit `kong.yml` and modify rate-limiting plugin config:

```yaml
- name: rate-limiting
  service: invoice-service
  config:
    minute: 200  # Change from 100 to 200
    limit_by: consumer
    policy: redis
```

Then reload:

```bash
./scripts/start-kong-gateway.sh load-config
```

### CORS Origins

Add custom origins in `kong.yml`:

```yaml
- name: cors
  config:
    origins:
      - http://custom.domain.com
      - https://custom.domain.com
```

### Custom Plugins

Place plugin configuration in `kong.yml` and reload configuration.

## Troubleshooting

### Kong Won't Start

```bash
# Check logs
docker logs invoiceshelf-kong

# Check database connectivity
docker logs invoiceshelf-kong-db

# Verify migrations
docker logs invoiceshelf-kong-migrations
```

### Services Not Reachable

```bash
# Check if services are running
./scripts/start-kong-gateway.sh status

# Check service logs
./scripts/start-kong-gateway.sh logs [service-name]

# Test service connectivity
curl -X GET http://localhost:8001/health
```

### High Latency

1. Check Grafana dashboards for bottlenecks
2. Review Jaeger traces for slow services
3. Check Redis performance
4. Verify database query times

### Rate Limiting Issues

```bash
# Check current rate limit config
curl -X GET http://localhost:8001/plugins

# View consumer quotas
curl -X GET http://localhost:8001/consumers/[username]/rate-limiting
```

## Performance Tuning

### Kong Configuration

In `docker-compose.kong.yml`:

```yaml
environment:
  KONG_WORKER_PROCESSES: auto  # Auto-detect CPU cores
  KONG_WORKER_CONNECTIONS: 10000  # Max connections per worker
  KONG_CLIENT_MAX_BODY_SIZE: 10m  # Max request body size
```

### Redis Optimization

For high throughput:

```bash
docker exec invoiceshelf-redis-services redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

### Database Connection Pooling

Microservices should use connection pooling:
- Min connections: 5
- Max connections: 20
- Idle timeout: 30s

## Backup & Recovery

### Backup Kong Configuration

```bash
# Export current configuration
curl -X GET http://localhost:8001/config > kong-backup.yaml

# Backup database
docker exec invoiceshelf-kong-db pg_dump -U kong kong > kong-db.sql
```

### Restore Configuration

```bash
# Load from backup
curl -X POST http://localhost:8001/config \
  -H "Content-Type: application/yaml" \
  -d @kong-backup.yaml
```

## Security Considerations

### API Keys

- Store API keys securely
- Rotate keys periodically
- Use separate keys for each client
- Implement key expiration

### JWT Tokens

- Use RS256 for signing (RSA)
- Set appropriate expiration times (default: 1 hour)
- Validate token claims
- Implement refresh token mechanism

### IP Whitelisting

Admin endpoints are restricted to:
- 127.0.0.1
- 10.0.0.0/8
- 172.16.0.0/12
- 192.168.0.0/16

Add additional IPs in `kong.yml` if needed.

## Support & Documentation

- [Kong Official Documentation](https://docs.konghq.com/)
- [Kong Gateway Admin API](https://docs.konghq.com/gateway/latest/admin-api/)
- [Kong Community Forum](https://discuss.konghq.com/)

## Common Tasks

### Reset Everything

```bash
# WARNING: This will delete all configuration
./scripts/start-kong-gateway.sh reset

# Or manually
docker-compose -f docker-compose.kong.yml down -v
```

### Update Kong Version

1. Edit docker-compose.kong.yml
2. Change `image: kong:3.4-alpine` to desired version
3. Restart: `./scripts/start-kong-gateway.sh restart`

### Enable SSL/TLS

See the Kong Admin GUI for certificate management:
1. http://localhost:8002 → Certificates
2. Add certificate for your domain
3. Configure in kong.yml

## File Structure

```
.
├── kong.yml                              # Main Kong configuration (declarative)
├── docker-compose.yml                    # Base services
├── docker-compose.kong.yml               # Kong infrastructure
├── docker-compose.microservices.yml      # 6 microservices
├── scripts/
│   ├── kong-startup.sh                  # Kong initialization
│   ├── start-kong-gateway.sh             # Main control script
│   └── init-databases.sql                # Database setup
├── config/
│   └── fluent-bit.conf                   # Log aggregation
└── KONG_SETUP.md                         # This file
```

## Version Information

- Kong: 3.4-alpine
- PostgreSQL: 15-alpine
- Redis: 7-alpine
- Prometheus: latest
- Grafana: latest
- Elasticsearch: 8.5.0
- Jaeger: latest
- Konga: next

---

Last Updated: 2026-08-30
