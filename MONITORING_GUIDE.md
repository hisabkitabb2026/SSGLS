# InvoiceShelf Monitoring Stack - Complete Guide

Comprehensive guide for using, managing, and troubleshooting the monitoring infrastructure.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Access Credentials](#access-credentials)
3. [Dashboard Guide](#dashboard-guide)
4. [Alert Management](#alert-management)
5. [Log Searching](#log-searching)
6. [Distributed Tracing](#distributed-tracing)
7. [Performance Baselines](#performance-baselines)
8. [Troubleshooting](#troubleshooting)
9. [Health Checks](#health-checks)

## Quick Start

### One-Command Setup

```bash
# Run the automated setup script
bash scripts/setup-monitoring.sh
```

### Manual Setup

```bash
# 1. Start the complete stack
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.monitoring.yml up -d

# 2. Verify services are running
docker-compose ps

# 3. Access Grafana and create dashboards
open http://localhost:3000
```

### Shutdown

```bash
# Stop all monitoring services
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.monitoring.yml down

# Stop and remove volumes
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.monitoring.yml down -v
```

## Access Credentials

### Default Credentials

| Service | URL | Username | Password | Notes |
|---------|-----|----------|----------|-------|
| Grafana | http://localhost:3000 | admin | admin | CHANGE IMMEDIATELY |
| Kibana | http://localhost:5601 | elastic | changeme | CHANGE FOR PRODUCTION |
| Prometheus | http://localhost:9090 | - | - | Read-only access |
| AlertManager | http://localhost:9093 | - | - | No auth by default |
| Jaeger | http://localhost:16686 | - | - | Read-only access |

### Change Grafana Admin Password

1. Log in to http://localhost:3000
2. Click profile icon (bottom left)
3. Select "Change password"
4. Enter new password (minimum 8 characters recommended)

### Elasticsearch Security (Production)

For production deployments:

```bash
# Enable authentication
xpack.security.enabled: true
discovery.type: single-node

# Set password
curl -X POST "localhost:9200/_security/user/elastic/_password?pretty" \
  -H 'Content-Type: application/json' \
  -d'{ "password" : "your-secure-password" }'

# Update Logstash and Kibana credentials in docker-compose
ELASTICSEARCH_USERNAME: elastic
ELASTICSEARCH_PASSWORD: your-secure-password
```

## Dashboard Guide

### System Overview Dashboard

**Purpose**: High-level view of infrastructure health

**Key Metrics**:
- **Active Services**: Gauge showing how many services are up (target: 6/6)
- **Request Rate**: Line chart of requests/sec by service
- **CPU Usage**: System CPU usage with warning (>80%) and critical (>90%) thresholds
- **Memory Usage**: Memory usage with warning (>85%) threshold
- **Error Rate**: Real-time error rates by service

**Typical Values**:
```
Normal State:
- Active Services: 6/6
- Request Rate: 50-200 req/s (varies by load)
- CPU Usage: 20-60%
- Memory Usage: 40-70%
- Error Rate: 0-2% (healthy)

Warning State:
- CPU > 80%
- Memory > 85%
- Error Rate > 5%
- Service down for >2 minutes
```

**Actions**:
- Click on any metric for drill-down analysis
- Set time range for historical investigation
- Check related alerts in AlertManager

### Service Metrics Dashboard

**Purpose**: Per-service performance analysis

**Key Metrics**:
- **Request Rate Distribution**: Stacked bar chart showing traffic per service
- **Request Latency Percentiles**: p95 and p99 response times
- **Status Code Distribution**: Success (2xx), client errors (4xx), server errors (5xx)
- **Request Throughput**: Data throughput in MB/s by service
- **Memory Usage by Service**: Per-container memory consumption

**Healthy Baseline**:
```
Request Latency:
- p95: < 500ms
- p99: < 1000ms

Throughput:
- Invoice Service: 10-30 MB/s
- Product Service: 5-15 MB/s
- Other Services: 5-20 MB/s

Memory per Service:
- Small service: 50-200 MB
- Medium service: 200-500 MB
- Large service: 500-1000 MB
```

**Common Issues**:
- Latency spike → Check database load
- High error rate → Check application logs
- Memory increase → Check for memory leaks

### Database Performance Dashboard

**Purpose**: Database health and query performance

**Key Metrics**:
- **Database Status**: MySQL, Redis, Elasticsearch indicators (green = up)
- **MySQL Query Rate**: Queries per second with breakdown (SELECT, INSERT, UPDATE)
- **Query Latency**: p95 and p99 query execution times
- **Connection Pool**: Active vs. maximum connections
- **Redis Operations**: Commands per second
- **Elasticsearch Cluster Health**: Shard status and heap usage

**Healthy Baseline**:
```
MySQL:
- Query Rate: 100-500 QPS
- p95 Latency: < 50ms
- p99 Latency: < 100ms
- Connection Usage: 20-60% of pool

Redis:
- Operations: 1000-5000 OPS
- Memory: 50-200 MB
- Hit Rate: > 90%
```

**When to Investigate**:
- Query latency > 200ms → Check slow query log
- Connection pool > 80% → Add connection pool or optimize connections
- High eviction rate → Increase Redis memory or optimize caching
- Elasticsearch CPU > 75% → Review heavy queries

### Logs Analysis Dashboard

**Purpose**: Log aggregation and error investigation

**Key Metrics**:
- **Log Level Distribution**: INFO, WARNING, ERROR, DEBUG counts over time
- **Logs by Service**: Log volume from each service
- **Recent Error Logs**: Table of most recent errors with details
- **Error Rate**: Real-time error log frequency
- **Exception Types**: Count of different exception/error types

**Useful Filters**:
```
# Search for specific service
service:invoice-service

# Find errors from timeframe
level:error AND @timestamp:[now-1h TO now]

# Track specific exception
exception_type:"NullPointerException"

# Business operations
event_type:"invoice_created"

# Database slow queries
type_of_log:database_query AND execution_time:[100 TO *]
```

## Alert Management

### Viewing Active Alerts

**In Grafana**:
1. Click "Alerting" → "Alert rules"
2. Filter by status (Firing, Pending, Inactive)
3. Click alert for details and history

**In AlertManager** (http://localhost:9093):
1. View current firing alerts
2. See alert grouping and routing
3. Acknowledge or silence alerts

### Understanding Alert Severity

**CRITICAL** - Immediate Action Required
- Service is down
- Database connection lost
- Disk space critical
- Memory exhausted

**WARNING** - Investigation Recommended
- High error rate (>5%)
- High latency (p95 > 1s)
- High CPU/Memory usage
- Network issues

### Managing Alerts

#### Acknowledge an Alert

```bash
# Via AlertManager API
curl -X POST http://localhost:9093/api/v1/alerts \
  -H 'Content-Type: application/json' \
  -d '{
    "status": "acknowledged",
    "labels": {
      "alertname": "HighErrorRate"
    }
  }'
```

#### Silence Alerts Temporarily

In AlertManager UI:
1. Click on alert
2. Click "Silence" button
3. Set duration (1 hour, 1 day, custom)
4. Add comment for context

#### Create Custom Alerts

Edit `prometheus-rules.yml`:

```yaml
- alert: CustomAlert
  expr: your_metric > threshold
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Alert summary"
    description: "Detailed description of alert"
```

Apply changes:
```bash
docker restart invoiceshelf-prometheus
```

## Log Searching

### Basic Search Syntax

```
# Find all errors in invoice-service
service:invoice-service AND level:ERROR

# Find slow database queries
type_of_log:database_query AND execution_time:[500 TO *]

# Find failed payments
event_type:payment AND status:failed

# Find exceptions from last hour
exception_type:* AND @timestamp:[now-1h TO now]
```

### Advanced Queries

#### Search by Status Code

```
# All 5xx errors
status_code:[500 TO 599]

# All 4xx client errors
status_code:[400 TO 499]

# Specific codes
status_code:(404 OR 403 OR 401)
```

#### Search by Response Time

```
# Slow requests (>1000ms)
response_time:[1000 TO *]

# Under 100ms
response_time:[0 TO 100]

# Between 100-500ms
response_time:[100 TO 500]
```

#### Time-Based Searches

```
# Last hour
@timestamp:[now-1h TO now]

# Last 24 hours
@timestamp:[now-24h TO now]

# Custom date range
@timestamp:[2024-01-01 TO 2024-01-31]

# Last 5 minutes
@timestamp:[now-5m TO now]
```

### Exporting Logs

**Export as CSV**:
1. Run search query
2. Click "Export" button
3. Select format (CSV, JSON)
4. Download file

**Via Elasticsearch API**:
```bash
curl -X POST "localhost:9200/logs-*/_search" \
  -H 'Content-Type: application/json' \
  -d '{
    "query": {
      "bool": {
        "must": [
          { "term": { "service": "invoice-service" } },
          { "term": { "level": "error" } }
        ]
      }
    },
    "size": 1000
  }' > error-logs.json
```

## Distributed Tracing

### Accessing Jaeger

1. Open http://localhost:16686
2. Select service from dropdown
3. Choose operation to trace
4. View trace timeline

### Understanding Traces

**Trace Components**:
- **Trace ID**: Unique identifier for complete request flow
- **Span**: Individual operation within trace
- **Span Duration**: Time for operation
- **Span Tags**: Metadata (service, operation, status, etc.)

### Tracing a Request

Example: Trace invoice creation workflow

1. Go to Jaeger → Select "invoice-service"
2. Choose operation "POST /invoices"
3. View timeline showing:
   - Invoice validation
   - Database insert
   - Cache update
   - Event publishing
   - Response time

### Common Trace Patterns

**Slow Request Trace**:
```
Total Duration: 2000ms
├─ Service A: 500ms (validation)
├─ Service B: 800ms (business logic) ← SLOW
├─ Service C: 400ms (database)
└─ Service D: 300ms (cache)
```

**Failed Request Trace**:
```
Total Duration: 150ms (failed early)
├─ Service A: 50ms (validation) ✓
├─ Service B: 100ms (error) ✗
└─ Service C: Not executed
```

## Performance Baselines

### CPU Usage Baseline

**Healthy CPU Usage by Load**:
```
Light Load (10 req/s):     15-30%
Medium Load (100 req/s):   35-55%
Heavy Load (500+ req/s):   60-80%
Overload (1000+ req/s):    > 80% (scaling needed)
```

### Memory Usage Baseline

**Service Memory Footprint**:
```
Invoice Service:   150-300 MB (normal operations)
Product Service:   200-400 MB
Expense Service:   100-250 MB
Customer Service:  80-200 MB
Settings Service:  50-150 MB
Transport Service: 120-280 MB
```

### Request Latency Baseline

**HTTP Request Latency**:
```
Fast Operations (GET):      p95 < 100ms,  p99 < 200ms
Standard Operations (POST): p95 < 500ms,  p99 < 1000ms
Complex Operations:         p95 < 1000ms, p99 < 2000ms
```

### Database Baseline

**MySQL Query Performance**:
```
SELECT queries:     p95 < 50ms,  p99 < 100ms
INSERT/UPDATE:      p95 < 100ms, p99 < 200ms
Complex joins:      p95 < 500ms, p99 < 1000ms
```

### Error Rate Baseline

```
Production:  < 1% (acceptable)
Staging:     < 5% (acceptable)
Development: < 10% (acceptable)
```

## Troubleshooting

### Issue: High Error Rate (>5%)

**Diagnosis Steps**:
1. Check error logs in Kibana
2. Look for common exception types
3. Check database status
4. Check service logs

**Common Causes & Fixes**:

| Cause | Fix |
|-------|-----|
| Database down | Restart MySQL, check connections |
| Invalid input | Check validation logic, API clients |
| Resource exhaustion | Scale up, add caching, optimize queries |
| Network issue | Check connectivity, DNS, firewall |
| Code bug | Review logs, check recent deployments |

### Issue: High Latency (p95 > 1000ms)

**Investigation**:
```bash
# Check database query latency
GET /logs/_search
{
  "query": {
    "bool": {
      "must": [
        { "match": { "type_of_log": "database_query" } },
        { "range": { "execution_time": { "gte": 500 } } }
      ]
    }
  }
}

# Check service performance
# In Prometheus:
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))
```

**Common Causes**:
- Slow database queries
- N+1 query problems
- Missing database indexes
- Resource contention
- Network latency

### Issue: Memory Leak

**Detection**:
1. Check memory dashboard over time
2. Memory steadily increasing despite stable load
3. Restart helps temporarily but issue returns

**Investigation**:
```bash
# Check container memory growth
docker stats invoiceshelf-invoice-service --no-stream

# Review logs for memory-heavy operations
service:invoice-service AND message:*memory*

# Check for connection leaks
# In database dashboard: Monitor active connections
```

**Solution**:
- Add memory limits in docker-compose
- Implement connection pooling
- Fix object creation/cleanup
- Add garbage collection tuning

### Issue: Service Down

**Quick Recovery**:
```bash
# Check service status
docker ps | grep invoiceshelf

# View logs
docker logs invoiceshelf-invoice-service --tail 50

# Restart service
docker restart invoiceshelf-invoice-service

# Check health endpoint
curl http://localhost:8001/health
```

**Root Cause Analysis**:
1. Check application logs
2. Review recent deployments
3. Check resource constraints
4. Monitor for crashes

## Health Checks

### System Health Check

```bash
#!/bin/bash
# Quick system health verification

echo "=== InvoiceShelf Monitoring Stack Health Check ==="

# Check Docker
echo -n "Docker: "
docker ps > /dev/null && echo "✓ OK" || echo "✗ FAILED"

# Check each service
services=(
  "elasticsearch:9200"
  "logstash:9600"
  "kibana:5601"
  "prometheus:9090"
  "grafana:3000"
  "alertmanager:9093"
  "jaeger:16686"
  "invoice-service:8001"
  "product-service:8003"
)

for service in "${services[@]}"; do
  host="${service%:*}"
  port="${service##*:}"
  echo -n "$host: "
  curl -s http://localhost:$port > /dev/null && echo "✓ OK" || echo "✗ FAILED"
done

# Check Prometheus targets
echo ""
echo "=== Prometheus Status ==="
curl -s http://localhost:9090/api/v1/query?query=up | jq '.data.result | length'
```

### Automated Health Checks

**In Prometheus**:
```
# Count healthy services
count(up == 1)

# Check alert status
ALERTS{alertstate="firing"}
```

**In Grafana**:
1. Create "Health Status" dashboard
2. Add panels for each critical component
3. Set critical thresholds
4. Share with team

## Configuration Updates

### Update Alert Thresholds

Edit `infrastructure/observability/prometheus-rules.yml`:

```yaml
- alert: HighCPUUsage
  expr: (100 - (avg by (instance) (irate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)) > 75  # Changed from 80
  for: 5m
```

Apply changes:
```bash
docker restart invoiceshelf-prometheus
```

### Add New Service to Prometheus

1. Edit `infrastructure/observability/prometheus.yml`
2. Add new job under `scrape_configs`
3. Restart Prometheus

```yaml
- job_name: 'new-service'
  static_configs:
    - targets: ['new-service:9090']
      labels:
        service: 'new-service'
        domain: 'newdomain'
```

### Update Log Patterns

Edit `infrastructure/observability/logstash-patterns.txt` and add patterns:

```
NEW_PATTERN \[%{TIMESTAMP_ISO8601:timestamp}\] custom_log_format
```

Edit `infrastructure/observability/logstash-pipeline.conf` and use pattern:

```
grok {
  match => { "message" => "%{NEW_PATTERN}" }
}
```

Restart Logstash:
```bash
docker restart invoiceshelf-logstash
```

## Next Steps

1. **[Customize Dashboards](MONITORING_SETUP.md#grafana-dashboards)** - Modify for your needs
2. **[Configure Notifications](MONITORING_SETUP.md#alertmanager-configuration)** - Set up Slack/PagerDuty
3. **[Implement SLOs](MONITORING_SETUP.md#next-steps)** - Define Service Level Objectives
4. **[Add Instrumentation](#)** - Instrument applications with Prometheus/Jaeger
5. **[Scale Monitoring](MONITORING_SETUP.md#performance-tuning)** - For production workloads
