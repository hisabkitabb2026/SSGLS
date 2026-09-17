# InvoiceShelf Comprehensive Monitoring Stack

Complete setup guide for ELK Stack, Prometheus, Grafana, Jaeger, and AlertManager for the InvoiceShelf microservices architecture.

## Overview

The monitoring stack consists of:

1. **Elasticsearch** - Log indexing and storage
2. **Logstash** - Log processing and forwarding
3. **Kibana** - Log visualization and search
4. **Prometheus** - Metrics collection and time-series database
5. **Grafana** - Metrics visualization and dashboarding
6. **AlertManager** - Alert management and routing
7. **Jaeger** - Distributed tracing
8. **Node Exporter** - System metrics
9. **cAdvisor** - Container metrics

## Quick Start

### Start the Complete Stack

```bash
# Start microservices with monitoring
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.monitoring.yml up -d

# Or with additional services
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.kong.yml \
               -f docker-compose.rabbitmq.yml \
               -f docker-compose.monitoring.yml up -d
```

### Access the Monitoring Interfaces

| Service | URL | Credentials |
|---------|-----|-------------|
| Grafana | http://localhost:3000 | admin / admin |
| Kibana | http://localhost:5601 | elastic / changeme |
| Prometheus | http://localhost:9090 | - |
| AlertManager | http://localhost:9093 | - |
| Jaeger UI | http://localhost:16686 | - |
| cAdvisor | http://localhost:8080 | - |

## Monitored Services

### 6 Microservices

1. **Invoice Service** (Port 8001, Metrics 9090)
   - Handles invoice operations
   - Domain: invoice

2. **Expense Service** (Port 8002, Metrics 9091)
   - Handles expense/product management
   - Domain: expense

3. **Product Service** (Port 8003, Metrics 9092)
   - Manages product catalog
   - Domain: product

4. **Customer Service** (Port 8004, Metrics 9093)
   - Manages customer portal
   - Domain: customer

5. **Settings Service** (Port 8005, Metrics 9094)
   - Handles configuration
   - Domain: settings

6. **Transport Service** (Port 8006, Metrics 9095)
   - Manages logistics and transport
   - Domain: transport

### Supporting Infrastructure

- **MySQL** - Primary database
- **Redis** - Cache layer
- **Kong** - API gateway (if enabled)
- **RabbitMQ** - Message broker (if enabled)

## Prometheus Configuration

### Scrape Configuration

Prometheus is configured to scrape metrics from:

1. **Service Metrics Endpoints**
   - All 6 microservices on their metrics ports (9090-9095)
   - Scrape interval: 10s
   - Timeout: 5s

2. **Infrastructure Components**
   - MySQL database
   - Redis cache
   - Elasticsearch
   - Logstash
   - Jaeger
   - Kong API Gateway

3. **System Metrics**
   - Node Exporter for host metrics
   - cAdvisor for container metrics
   - Prometheus self-metrics

### Metric Labels

Each scraped metric is labeled with:

- `service`: Service name (e.g., invoice-service)
- `domain`: Domain/context (e.g., invoice)
- `instance_id`: Unique instance identifier
- `instance_type`: Type of component (host, container, database, etc.)
- `environment`: Environment tag (production, staging, dev)

### Retention Policy

- **Storage**: 30 days of metrics retention
- **Evaluation**: Alert rules evaluated every 15 seconds
- **Scrape**: Metrics collected every 10-15 seconds

## Alert Rules

### Alert Categories

#### 1. System Alerts
- High CPU usage (> 80%)
- High memory usage (> 85%)
- Low disk space (< 10%)

#### 2. Service Availability
- Service down (no response for 2+ minutes)
- High error rate (> 5% of requests)
- High request latency (p95 > 1s)

#### 3. Database Alerts
- MySQL down or connection pool exhaustion
- Redis down
- Elasticsearch down or high heap usage
- Database slow queries

#### 4. Container Alerts
- Container restart loops
- High container memory usage
- Resource limit violations

#### 5. Network Alerts
- High network traffic
- Network errors or packet loss

#### 6. Application Alerts
- Error rate spikes
- Latency increases
- Connection pool exhaustion

### Alert Severity Levels

- **CRITICAL**: Immediate action required (service down, data loss risk)
- **WARNING**: Investigation recommended (performance degradation, resource warnings)
- **INFO**: Informational (configuration changes, routine operations)

## Logstash Pipelines

### Input Sources

1. **TCP/UDP** - Port 5000
   - JSON formatted logs
   - Low-latency delivery

2. **HTTP** - Port 8080
   - REST API for log submission
   - Batch submissions

3. **Filebeat/Metricbeat** - Port 5044
   - Agent-based log shipping
   - Pre-parsing support

4. **File Input**
   - Tail application log files
   - Multiline log support

### Processing Pipeline

Logs are processed through:

1. **JSON Parsing** - Extract structured log data
2. **Grok Patterns** - Parse unstructured logs using custom patterns
3. **Service Identification** - Assign service name and domain
4. **Log Level Mapping** - Normalize severity levels
5. **Sensitive Data Removal** - Redact passwords, tokens, API keys
6. **Timestamp Normalization** - Parse timestamps to ISO format

### Custom Grok Patterns

Patterns support parsing:
- Laravel/PHP application logs
- Microservice request/response logs
- Database queries
- API gateway access logs
- Distributed trace information
- Authentication events
- Business events (invoice created, payment processed)
- Cache operations
- Queue messages
- File uploads and reports
- Payment processing events
- Webhook deliveries
- Background job execution

### Output Destinations

1. **Elasticsearch** - Primary storage for all logs
   - Index pattern: `logs-YYYY.MM.DD`
   - Error logs: `logs-errors-YYYY.MM.DD`

2. **AlertManager** - Send critical events
   - Automatically alerts on error conditions

3. **Console** - Debug output during development

## Elasticsearch Templates

### Index Settings

- **Shards**: 1 (single node setup)
- **Replicas**: 0
- **Codec**: Best compression
- **Refresh Interval**: 5 seconds

### Field Mappings

Includes mapping for:

- Core fields: timestamp, message, level, service, domain
- HTTP fields: method, path, status code, response time, latency
- Database fields: query type, table name, execution time
- Tracing fields: trace_id, span_id, parent_span_id
- Security fields: user_id, username, client_ip
- Performance fields: CPU, memory, disk usage
- Business fields: entity types, event types, transaction IDs
- Error fields: exception type, stack trace, error code
- GeoIP fields: For IP-based geographic information

## Grafana Dashboards

### Available Dashboards

1. **System Overview** (`01-system-overview.json`)
   - Active services gauge
   - Request rate by service
   - CPU and memory usage
   - Error rates
   - Time range: Last 1 hour

2. **Service Metrics** (`02-service-metrics.json`)
   - Request rate distribution by service
   - Request latency percentiles (p95, p99)
   - Request status distribution
   - Request throughput
   - Service memory usage
   - Time range: Last 1 hour

3. **Database Performance** (`03-database-performance.json`)
   - Database status indicators
   - MySQL query rate and latency
   - Connection pool usage
   - Redis operations
   - Elasticsearch health
   - Time range: Last 1 hour

4. **Logs Analysis** (`04-logs-analysis.json`)
   - Log level distribution
   - Logs by service
   - Recent error logs table
   - Error rate over time
   - Exception types
   - Time range: Last 24 hours

### Dashboard Features

- Auto-refresh every 30 seconds
- Variable timeframe selection
- Detailed legends with statistics
- Multi-metric overlays
- Color-coded thresholds
- Drill-down capabilities

## Jaeger Distributed Tracing

### Tracing Configuration

- **Memory Storage**: Stores up to 10,000 traces
- **Multiple Protocols Supported**:
  - Jaeger compact (6831/udp)
  - Jaeger binary (6832/udp)
  - Zipkin compact (5775/udp)
  - HTTP collector (14268)
  - gRPC collector (14250)

### Integration with Services

Each microservice should:

1. Initialize Jaeger client with service name
2. Extract/inject span context from requests
3. Record span timing and tags
4. Send traces to Jaeger agent (localhost:6831)

### Span Tags

Standard tags for all spans:
- `service.name`: Service identifier
- `span.kind`: client, server, producer, consumer
- `http.method`: HTTP method
- `http.url`: Request URL
- `http.status_code`: Response status
- `db.type`: Database type (mysql, redis, etc.)
- `db.statement`: Query/command executed

## AlertManager Configuration

### Alert Routing

Alerts are routed based on:

1. **Severity Level**
   - Critical → immediate notification (5 min repeat)
   - Warning → standard notification (1 hour repeat)

2. **Component Type**
   - Services → service alerts channel
   - Database → database alerts channel
   - System → system critical channel

3. **Service Name**
   - Each service domain has routing rules

### Notification Channels

Configure in `alertmanager.yml`:

- **Slack** - Real-time notifications
- **PagerDuty** - Critical incident routing
- **Email** - Digest notifications
- **Webhooks** - Custom integrations

### Alert Inhibition Rules

Prevents alert storms by suppressing:

- Application alerts when service is down
- Query errors when database is down
- Memory errors when node is offline
- Network errors when host is unreachable

## Configuration Files

### Core Configuration Files

1. **prometheus.yml** - Prometheus scrape configuration
2. **prometheus-rules.yml** - Alert rules definitions
3. **alertmanager.yml** - Alert routing and notifications
4. **logstash-pipeline.conf** - Log processing pipeline
5. **logstash-patterns.txt** - Custom grok patterns
6. **elasticsearch-templates.json** - Index templates

### Grafana Provisioning

```
infrastructure/observability/grafana-provisioning/
├── datasources/
│   └── prometheus.yml      # Data source configurations
├── dashboards/
│   └── provisioning.yml    # Dashboard provider configuration
└── notifiers/
    └── (notification channels)
```

### Dashboards

```
infrastructure/observability/dashboards/
├── 01-system-overview.json
├── 02-service-metrics.json
├── 03-database-performance.json
└── 04-logs-analysis.json
```

## Performance Tuning

### Elasticsearch Optimization

```yaml
# Increase heap size for large deployments
ES_JAVA_OPTS: "-Xms2g -Xmx2g"

# Configure index lifecycle
PUT _ilm/policy/logs-policy
{
  "policy": "logs-policy",
  "phases": {
    "hot": {
      "min_age": "0d",
      "actions": {
        "rollover": { "max_size": "50GB" }
      }
    },
    "warm": {
      "min_age": "30d",
      "actions": {
        "set_priority": { "priority": 50 }
      }
    },
    "delete": {
      "min_age": "90d",
      "actions": {
        "delete": {}
      }
    }
  }
}
```

### Prometheus Optimization

```yaml
# Increase retention for important metrics
--storage.tsdb.retention.time=90d
--storage.tsdb.retention.size=50GB

# Enable compression
--storage.tsdb.wal-compression
```

### Grafana Optimization

- Adjust refresh intervals based on workload
- Use query caching for common dashboards
- Implement panel-level query limits
- Optimize large dashboard sizes

## Troubleshooting

### Common Issues

#### 1. Services Not Scraped

**Problem**: Prometheus shows targets as "Down"

**Solution**:
```bash
# Check service health
curl http://localhost:8001/health
curl http://localhost:8002/health

# Verify service metrics endpoints
curl http://invoice-service:9090/metrics

# Check network connectivity
docker exec invoiceshelf-prometheus ping invoice-service
```

#### 2. Logs Not Appearing in Elasticsearch

**Problem**: No logs in Kibana

**Solution**:
```bash
# Verify Logstash is running
docker logs invoiceshelf-logstash

# Check Elasticsearch indices
curl http://localhost:9200/_cat/indices

# Send test log
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"message":"test", "level":"info"}'
```

#### 3. Alert Manager Not Sending Notifications

**Problem**: Alerts not reaching Slack/PagerDuty

**Solution**:
```bash
# Check AlertManager logs
docker logs invoiceshelf-alertmanager

# Verify webhook URL in alertmanager.yml
# Test webhook manually
curl -X POST http://webhook-url \
  -H 'Content-Type: application/json' \
  -d '{"text":"test"}'
```

#### 4. High Memory Usage

**Problem**: Elasticsearch or Prometheus consuming excessive memory

**Solution**:
```bash
# Reduce retention
--storage.tsdb.retention.time=15d

# Lower scrape frequency
global:
  scrape_interval: 30s

# Adjust heap size
ES_JAVA_OPTS: "-Xms512m -Xmx512m"
```

## Maintenance Tasks

### Daily

- Monitor alert frequency
- Review error logs for patterns
- Check disk space usage

### Weekly

- Review dashboard trends
- Optimize slow queries
- Archive important alerts

### Monthly

- Update alert thresholds based on baselines
- Rotate logs (if not using ILM)
- Capacity planning review
- Performance optimization

## Security Considerations

### Authentication

- Change default Grafana admin password
- Set Elasticsearch authentication credentials
- Configure AlertManager webhook authentication

### Data Protection

- Enable TLS for inter-service communication
- Encrypt sensitive fields in logs
- Configure RBAC for Grafana teams

### Log Sensitive Data

All passwords, API keys, tokens automatically redacted via Logstash:
- `password=***REDACTED***`
- `api_key=***REDACTED***`
- `token=***REDACTED***`
- `Bearer ***REDACTED***`

## Integration with CI/CD

### GitHub Actions Integration

```yaml
- name: Check Prometheus Configuration
  run: |
    docker run --rm -v $(pwd)/infrastructure/observability:/etc/prometheus \
      prom/prometheus:latest \
      --config.file=/etc/prometheus/prometheus.yml \
      --query.lookback-delta=5m \
      --query.max-samples=100000000

- name: Validate AlertManager Config
  run: |
    docker run --rm -v $(pwd)/infrastructure/observability:/alertmanager \
      prom/alertmanager:latest \
      --config.file=/alertmanager/alertmanager.yml
```

## Backup and Recovery

### Backup Important Data

```bash
# Backup Grafana dashboards
docker exec invoiceshelf-grafana \
  grafana-cli admin export-dashboard > backup-dashboards.json

# Backup Prometheus configuration
docker cp invoiceshelf-prometheus:/etc/prometheus backup-prometheus/

# Backup Elasticsearch indices
curl http://localhost:9200/_snapshot/backup/_all
```

## Next Steps

1. **Configure Slack/PagerDuty Webhooks**
   - Update webhook URLs in `alertmanager.yml`
   - Test notification delivery

2. **Customize Alert Thresholds**
   - Adjust thresholds in `prometheus-rules.yml`
   - Base on your normal baseline metrics

3. **Instrument Services**
   - Add Prometheus client libraries
   - Implement Jaeger tracing
   - Configure log forwarding

4. **Set Up SLOs**
   - Define Service Level Objectives
   - Create alerting rules for SLO violations
   - Track compliance dashboards

5. **Implement On-Call Rotation**
   - Configure PagerDuty escalation policies
   - Define incident response procedures
   - Document runbooks

## References

- [Prometheus Documentation](https://prometheus.io/docs/)
- [Grafana Documentation](https://grafana.com/docs/grafana/)
- [Elasticsearch Documentation](https://www.elastic.co/guide/index.html)
- [Logstash Documentation](https://www.elastic.co/guide/en/logstash/current/index.html)
- [Jaeger Documentation](https://www.jaegertracing.io/docs/)
- [AlertManager Documentation](https://prometheus.io/docs/alerting/latest/overview/)
