# Kong API Gateway Configuration Summary

## Complete Configuration Overview

This document summarizes the Kong API Gateway setup for InvoiceShelf microservices.

---

## 1. Services Configuration

### Service Definitions (6 DDD Microservices)

```yaml
Services:
  1. invoice-service
     URL: http://invoice-service:8001/api/v1
     Port: 8001
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:invoice, ddd

  2. expense-service
     URL: http://expense-service:8002/api/v1
     Port: 8002
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:expense, ddd

  3. product-service
     URL: http://product-service:8003/api/v1
     Port: 8003
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:product, ddd

  4. customer-service
     URL: http://customer-service:8004/api/v1
     Port: 8004
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:customer, ddd

  5. settings-service
     URL: http://settings-service:8005/api/v1
     Port: 8005
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:settings, ddd

  6. transport-service
     URL: http://transport-service:8006/api/v1
     Port: 8006
     Timeout: connect=6s, read=60s, write=60s
     Retries: 3
     Tags: domain:transport, ddd

  7. health-service
     URL: http://app:9000
     Path: /health
     Tags: health-check, monitoring

  8. web-service
     URL: http://app:9000
     Path: /
     Tags: web, frontend
```

---

## 2. Routes Configuration

### Invoice Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| invoice-list | `/api/v1/invoices` | GET |
| invoice-create | `/api/v1/invoices` | POST |
| invoice-detail | `/api/v1/invoices/{id}` | GET |
| invoice-update | `/api/v1/invoices/{id}` | PUT, PATCH |
| invoice-delete | `/api/v1/invoices/{id}` | DELETE |
| invoice-pdf | `/api/v1/invoices/{id}/pdf` | GET, POST |

### Expense Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| expense-list | `/api/v1/expenses` | GET |
| expense-create | `/api/v1/expenses` | POST |
| expense-detail | `/api/v1/expenses/{id}` | GET |
| expense-update | `/api/v1/expenses/{id}` | PUT, PATCH |
| expense-delete | `/api/v1/expenses/{id}` | DELETE |

### Product Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| product-list | `/api/v1/products` | GET |
| product-create | `/api/v1/products` | POST |
| product-detail | `/api/v1/products/{id}` | GET |
| product-update | `/api/v1/products/{id}` | PUT, PATCH |
| product-delete | `/api/v1/products/{id}` | DELETE |

### Customer Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| customer-list | `/api/v1/customers` | GET |
| customer-create | `/api/v1/customers` | POST |
| customer-detail | `/api/v1/customers/{id}` | GET |
| customer-update | `/api/v1/customers/{id}` | PUT, PATCH |
| customer-delete | `/api/v1/customers/{id}` | DELETE |
| customer-portal | `/portal/{slug}/*` | GET, POST |

### Settings Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| settings-list | `/api/v1/settings` | GET |
| settings-update | `/api/v1/settings` | PUT, PATCH |
| settings-detail | `/api/v1/settings/{key}` | GET |
| admin-settings | `/api/v1/admin/settings/*` | GET, PUT, PATCH |

### Transport Routes

| Route Name | Path | Methods |
|-----------|------|---------|
| transport-list | `/api/v1/transport` | GET |
| transport-create | `/api/v1/transport` | POST |
| transport-detail | `/api/v1/transport/{id}` | GET |
| transport-update | `/api/v1/transport/{id}` | PUT, PATCH |
| transport-delete | `/api/v1/transport/{id}` | DELETE |

---

## 3. Global Plugins

### CORS Plugin

```yaml
Plugin: cors
Scope: Global
Configuration:
  Origins:
    - http://invoiceshelf.test
    - http://localhost:5173
    - http://localhost:3000
    - http://localhost:8080
    - https://invoiceshelf.test
    - https://localhost:5173
    - https://app.invoiceshelf.local
  Methods: GET, HEAD, PUT, PATCH, POST, DELETE, OPTIONS
  Headers Allowed:
    - Accept, Accept-Version, Content-Length, Content-MD5
    - Content-Type, Date
    - X-Auth-Token, X-API-Key, X-Company-ID
    - Authorization, X-Requested-With
    - X-Trace-ID, X-Request-ID
  Exposed Headers:
    - X-Auth-Token, X-RateLimit-*, X-Request-ID, X-Trace-ID
  Credentials: true
  Max Age: 3600s
```

### Request Transformer (Trace ID Injection)

```yaml
Plugin: request-transformer
Scope: Global
Configuration:
  Add Headers:
    - X-Kong-Request-Time: $msec
    - X-Request-ID: $request_id
  Add If Not Exists:
    - X-Trace-ID: $http_x_trace_id
```

### Response Transformer

```yaml
Plugin: response-transformer
Scope: Global
Configuration:
  Add Headers:
    - X-API-Version: v1
    - X-Powered-By: InvoiceShelf
    - X-Request-ID: $request_id
  Remove Headers:
    - Server
```

### Bot Detection

```yaml
Plugin: bot-detection
Scope: Global
Configuration:
  Denylist:
    - "bot"
    - "crawler"
    - "spider"
  Allow Crawlers: false
```

### Prometheus Metrics

```yaml
Plugin: prometheus
Scope: Global
Configuration:
  Prefix: kong
  Return All Metrics: true
  Tracked Metrics:
    - request_count
    - latency
    - status_count
    - unique_users
    - request_per_user
    - upstream_latency
    - request_size
    - response_size
```

---

## 4. Service-Specific Plugins

### JWT Authentication

Applied to all 6 microservices (invoice, expense, product, customer, settings, transport)

```yaml
Plugin: jwt
Configuration:
  Key Claim Name: iss
  Secret Is Base64: false
  Cookie Names:
    - jwt
    - auth_token
  Claims to Verify:
    - exp (expiration)
    - nbf (not before)
  Algorithms:
    - HS256
    - HS512
    - RS256
  Audience: invoiceshelf-api
```

### Rate Limiting

Applied to all 6 microservices + health check

```yaml
Plugin: rate-limiting
API Services Configuration:
  Requests/Minute: 100
  Limit By: consumer
  Policy: redis
  Fault Tolerant: true
  Redis Host: redis
  Redis Port: 6379

Health Check Configuration:
  Requests/Second: 100
  Limit By: IP
  Policy: local
  Fault Tolerant: true
```

### Proxy Caching

Applied to: invoice-service, product-service

```yaml
Plugin: proxy-cache
Configuration:
  Content Types: application/json
  Cache TTL: 300 seconds (5 minutes)
  Strategy: memory
  Cache Control: enabled
  Vary By Headers: X-Company-ID
  Vary By Query Parameters:
    - page, limit, sort, filter
```

### Request Size Limiting

Applied to: invoice-service, expense-service, product-service

```yaml
Plugin: request-size-limiting
Configuration:
  Max Payload Size: 100 MB
  Size Units: megabytes
```

### HTTP Logging

```yaml
Plugin: http-log
Scope: Global
Configuration:
  Endpoint: http://logging-aggregator:9000/logs
  Method: POST
  Timeout: 1000ms
  Keepalive: 60s
  Content Type: application/json
  Flush Timeout: 2s
  Retry Count: 5
  Queue Size: 1000
```

---

## 5. Infrastructure Stack

### Core Components

```
Kong API Gateway
├── Port 8000 - HTTP Proxy
├── Port 8443 - HTTPS Proxy
├── Port 8001 - Admin API (HTTP)
├── Port 8444 - Admin API (HTTPS)
├── Port 8002 - Admin GUI
├── Port 7946 - Cluster
└── Health: http://localhost:8001/status
```

### Database

```
PostgreSQL 15
├── Container: kong-db
├── Port: 5432
├── Database: kong
├── User: kong
├── Volume: kong-db-data
└── Health: pg_isready check
```

### Cache Layer

```
Redis 7
├── Container: redis
├── Port: 6379
├── Authentication: requirepass
├── Volume: redis-data
├── Use Cases:
│   ├── Rate limiting
│   ├── Caching (proxy-cache)
│   └── Session storage
└── Health: PING check
```

### Monitoring Stack

```
Prometheus
├── Port: 9090
├── Config: prometheus.yml
├── Data: prometheus-data
└── Scrape Interval: 15s

Grafana
├── Port: 3000
├── Default Credentials: admin / admin_password
├── Data: grafana-data
└── Provisioned Dashboards: Kong, Microservices

Jaeger
├── Port: 16686 (Web UI)
├── Port: 6831 (Agent UDP)
├── Port: 14268 (Collector HTTP)
└── Features: Distributed tracing, Trace visualization
```

### Logging Stack

```
Elasticsearch
├── Port: 9200
├── Single Node: true
├── Volume: elasticsearch-data
└── Data: invoiceshelf

Kibana
├── Port: 5601
├── Connected to: elasticsearch:9200
└── Index Patterns: kong-logs-*, microservices-logs-*

Fluent Bit (Log Aggregator)
├── Port: 9000 (HTTP listener)
├── Port: 24224 (Forward protocol)
├── Input: Kong logs, Docker logs
└── Output: Elasticsearch, Kibana
```

### Administration Tools

```
Konga
├── Port: 1337
├── Database: PostgreSQL (kong-db)
├── Features:
│   ├── Kong Admin GUI
│   ├── Service management
│   ├── Route management
│   └── Plugin management
└── Volume: konga-data
```

---

## 6. Docker Compose Configuration

### Compose Files Structure

```
docker-compose.yml
├── Base services (optional main app)
├── Network: invoiceshelf-network
└── Volumes: shared storage

docker-compose.kong.yml
├── Kong Gateway components
├── PostgreSQL database
├── Redis cache
├── Monitoring stack (Prometheus, Grafana)
├── Logging stack (Elasticsearch, Kibana, Konga)
├── Tracing (Jaeger)
└── Documentation (Swagger UI)

docker-compose.microservices.yml
├── 6 Microservices (8001-8006)
├── MySQL database (shared)
├── Redis (shared)
├── Logging Aggregator
├── Jaeger
└── Network: invoiceshelf-network
```

---

## 7. Request Flow Diagram

```
┌─────────────┐
│   Client    │
└──────┬──────┘
       │
       │ HTTP Request
       ▼
┌──────────────────────────────────────────┐
│         Kong Proxy (8000)                 │
├──────────────────────────────────────────┤
│ 1. CORS Validation                       │
│ 2. Request Transformer (add trace ID)    │
│ 3. Bot Detection                         │
│ 4. Rate Limiting Check                   │
└──────┬───────────────────────────────────┘
       │
       ▼
┌──────────────────────────────────────────┐
│    Service Authentication                 │
├──────────────────────────────────────────┤
│ 5. JWT Validation                        │
│ 6. API Key Validation (if applicable)    │
└──────┬───────────────────────────────────┘
       │
       ├─────────────────┬──────────────┬──────────────┬──────────────┬──────────────┐
       │                 │              │              │              │              │
       ▼                 ▼              ▼              ▼              ▼              ▼
  ┌────────┐       ┌────────┐    ┌────────┐    ┌────────┐    ┌────────┐    ┌────────┐
  │Invoice │       │Expense │    │Product │    │Customer│    │Settings│    │Transport│
  │Service │       │Service │    │Service │    │Service │    │Service │    │Service │
  │(8001)  │       │(8002)  │    │(8003)  │    │(8004)  │    │(8005)  │    │(8006)  │
  └────┬───┘       └────┬───┘    └────┬───┘    └────┬───┘    └────┬───┘    └────┬───┘
       │                │             │             │             │             │
       └────────────────┼─────────────┼─────────────┼─────────────┼─────────────┘
                        │
       ┌────────────────┼─────────────────────────────────────────┐
       │                │                                          │
       ▼                ▼                                          ▼
  ┌─────────┐     ┌──────────┐                          ┌──────────────┐
  │  MySQL  │     │  Redis   │                          │  Logging &   │
  │Database │     │  Cache   │                          │  Metrics     │
  └─────────┘     └──────────┘                          └──────────────┘
                                                           ├─ Elasticsearch
                                                           ├─ Prometheus
                                                           ├─ Jaeger
                                                           └─ Fluent Bit
```

---

## 8. Plugin Chain Execution Order

```
Global Plugins (executed for all requests):
1. CORS
2. Request Transformer (trace ID injection)
3. Request Size Limiting
4. Bot Detection
5. Rate Limiting
6. Response Transformer
7. HTTP Logging
8. Proxy Cache (for GET requests)

Service-Specific Plugins:
9. JWT Authentication
10. ACL (if applicable)
11. Response Compression (optional)
```

---

## 9. Environment Variables

### Kong Environment Variables

```env
# Database
KONG_DATABASE=postgres
KONG_PG_HOST=kong-db
KONG_PG_PORT=5432
KONG_PG_USER=kong
KONG_PG_PASSWORD=kong_password
KONG_PG_DATABASE=kong

# Server
KONG_ADMIN_LISTEN=0.0.0.0:8001, 0.0.0.0:8444 ssl
KONG_PROXY_LISTEN=0.0.0.0:8000, 0.0.0.0:8443 ssl

# Performance
KONG_WORKER_PROCESSES=auto
KONG_WORKER_CONNECTIONS=10000
KONG_CLIENT_MAX_BODY_SIZE=10m

# Logging
KONG_LOG_LEVEL=info
KONG_PROXY_ACCESS_LOG=/var/log/kong/access.log
KONG_PROXY_ERROR_LOG=/var/log/kong/error.log

# Plugins
KONG_PLUGINS=bundled,jwt,rate-limiting,cors,proxy-cache,key-auth,acl,oauth2,prometheus
```

### Microservice Environment Variables

```env
SERVICE_NAME=[service-name]
SERVICE_PORT=[8001-8006]
DATABASE_URL=mysql://app:password@mysql:3306/invoiceshelf_[domain]
REDIS_URL=redis://redis:6379/[1-6]
LOG_LEVEL=info
ENABLE_METRICS=true
METRICS_PORT=[9090-9095]
```

---

## 10. Health Check Endpoints

### Kong Health Checks

```
GET http://localhost:8001/status
GET http://localhost:8000/health
GET http://localhost:8000/ping
GET http://localhost:8000/health/ready
GET http://localhost:8000/health/live
```

### Microservice Health Checks

```
GET http://localhost:8001/health  (Invoice)
GET http://localhost:8002/health  (Expense)
GET http://localhost:8003/health  (Product)
GET http://localhost:8004/health  (Customer)
GET http://localhost:8005/health  (Settings)
GET http://localhost:8006/health  (Transport)
```

### Database Health Checks

```
PostgreSQL: pg_isready -U kong
Redis: redis-cli PING
MySQL: mysqladmin ping
Elasticsearch: curl http://localhost:9200
```

---

## 11. Metrics & Monitoring

### Prometheus Targets

```yaml
- Kong Proxy: :8000/metrics
- Kong Admin: :8001/metrics
- Microservices: :9090-9095/metrics
```

### Key Metrics

```
kong_http_requests_total
  ├─ service
  ├─ route
  ├─ method
  └─ status

kong_request_latency_ms
  ├─ service
  └─ status

kong_upstream_latency_ms
kong_cache_hit_ratio
kong_bandwidth_bytes
kong_connections_*
```

### Grafana Queries

```promql
# Request rate by service
rate(kong_http_requests_total[1m]) by (service)

# Average latency by service
avg(rate(kong_request_latency_ms_sum[1m]) / rate(kong_request_latency_ms_count[1m])) by (service)

# Error rate
sum(rate(kong_http_requests_total{status=~"5.."}[1m])) / sum(rate(kong_http_requests_total[1m]))

# Cache hit ratio
kong_cache_hit_ratio
```

---

## 12. Backup & Recovery Procedures

### Backup Kong Configuration

```bash
# Export declarative config
curl http://localhost:8001/config -o kong-config-backup.yaml

# Backup database
docker exec kong-db pg_dump -U kong kong -Fc > kong-db.backup
```

### Backup Microservice Databases

```bash
# All databases
docker exec mysql mysqldump -u app -p -A > all-databases.sql

# Specific database
docker exec mysql mysqldump -u app -p invoiceshelf_invoice > invoice.sql
```

### Recovery Procedures

```bash
# Restore Kong config
curl -X POST http://localhost:8001/config \
  -H "Content-Type: application/yaml" \
  -d @kong-config-backup.yaml

# Restore Kong database
docker exec -i kong-db pg_restore -U kong -d kong < kong-db.backup

# Restore MySQL
docker exec -i mysql mysql -u app -p < all-databases.sql
```

---

## 13. Scaling & Performance Tuning

### Kong Scaling

```yaml
# In docker-compose.kong.yml
environment:
  KONG_WORKER_PROCESSES: auto
  KONG_WORKER_CONNECTIONS: 10000
  KONG_UPSTREAM_KEEPALIVE: 60
```

### Microservice Scaling

```bash
# Scale to N replicas
docker-compose up -d --scale invoice-service=3

# Or use management script
./scripts/start-kong-gateway.sh scale invoice-service 3
```

### Load Balancing

Kong uses automatic load balancing across service replicas:
- Algorithm: Round-robin (default)
- Health checks: Automatic service removal on failure
- Retries: Configurable per service

---

## 14. Security Configuration

### Authentication Methods

1. **JWT Tokens**
   - Algorithm: HS256, HS512, RS256
   - Audience: invoiceshelf-api
   - Expiration: Configurable

2. **API Keys**
   - Header: X-API-Key
   - Query Parameter: apikey
   - Cookie: api_key

3. **OAuth2**
   - Scope: invoices.*, estimates.*, customers.*, payments.*, admin.*
   - TTL: 2 hours
   - Refresh: 30 days

### IP Whitelisting

```yaml
Admin Service (8005):
  Whitelist:
    - 127.0.0.1
    - 10.0.0.0/8
    - 172.16.0.0/12
    - 192.168.0.0/16
```

### Rate Limiting

```
Default: 100 requests/minute per consumer
Health Check: 100 requests/second per IP
Burstable: true (allows brief spikes)
```

---

## 15. Troubleshooting Guide

### Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| Kong won't start | DB unavailable | Check `kong-db` status and logs |
| Service unreachable | Network issues | Verify service is running, check logs |
| Rate limit errors | Quota exceeded | Check consumer limits, reset if needed |
| High latency | Database slow | Check MySQL/Redis performance |
| Logs not aggregating | Fluent Bit down | Restart logging-aggregator service |
| Metrics missing | Prometheus down | Verify prometheus container is running |

---

Last Updated: 2026-08-30
