# Observability Infrastructure

Enterprise-grade monitoring and observability stack for InvoiceShelf microservices.

## Contents

### Configuration Files

| File | Purpose | Size |
|------|---------|------|
| `prometheus.yml` | Metrics collection configuration | 7 KB |
| `prometheus-rules.yml` | Alert rules (50+ alerts) | 25 KB |
| `alertmanager.yml` | Alert routing and notification | 6 KB |
| `elasticsearch-templates.json` | Index mappings and settings | 15 KB |
| `logstash-pipeline.conf` | Log processing pipeline | 12 KB |
| `logstash-patterns.txt` | Custom Grok patterns (25+) | 8 KB |

### Grafana Provisioning

```
grafana-provisioning/
├── datasources/
│   └── prometheus.yml          # Data source configuration
└── dashboards/
    └── provisioning.yml        # Dashboard provider config
```

### Dashboards

```
dashboards/
├── 01-system-overview.json         # Infrastructure health
├── 02-service-metrics.json         # Per-service performance
├── 03-database-performance.json    # Database & cache metrics
└── 04-logs-analysis.json           # Log aggregation & search
```

## Quick Start

### 1. Deploy Stack

```bash
cd ../..
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.monitoring.yml up -d
```

### 2. Access Interfaces

- **Grafana**: http://localhost:3000 (admin/admin)
- **Kibana**: http://localhost:5601
- **Prometheus**: http://localhost:9090
- **AlertManager**: http://localhost:9093
- **Jaeger**: http://localhost:16686

### 3. Verify Setup

```bash
# Check services
docker-compose ps

# Verify metrics
curl http://localhost:9090/api/v1/targets

# Check logs
curl http://localhost:9200/_cat/indices
```

## Components

### Prometheus
- **Metrics Database**: Time-series database for all metrics
- **Scrape Config**: Monitors all 6 microservices + infrastructure
- **Alert Rules**: 50+ predefined alert rules
- **Retention**: 30 days of metrics

**Key Metrics**:
- Request rate, latency, errors (per service)
- CPU, memory, disk usage
- Database query rate and latency
- Cache operations
- Container metrics

### Elasticsearch
- **Log Storage**: Central log indexing and storage
- **Index Pattern**: logs-YYYY.MM.dd (daily rotation)
- **Mappings**: 100+ field types
- **Retention**: Configurable via ILM

**Log Types**:
- Application logs
- Request/response logs
- Database query logs
- Authentication events
- Business events
- Error traces

### Logstash
- **Input Sources**: TCP, UDP, HTTP, Filebeat
- **Processing**: JSON parsing, Grok extraction, field enrichment
- **Custom Patterns**: 25+ patterns for application logs
- **Outputs**: Elasticsearch, AlertManager, Console

**Features**:
- Multiline log support
- Sensitive data redaction
- Service identification
- Log level normalization

### Kibana
- **Log Search**: Full-text search with Lucene syntax
- **Visualizations**: Pre-built dashboards
- **Analytics**: Log analysis and trending
- **Export**: CSV/JSON export capabilities

### Grafana
- **Dashboards**: 4 comprehensive dashboards
- **Data Sources**: Prometheus, Elasticsearch, Jaeger, AlertManager
- **Alerts**: Integrated with Prometheus alerts
- **Sharing**: Team collaboration and sharing

### AlertManager
- **Alert Routing**: Severity and service-based routing
- **Notifications**: Slack, PagerDuty, Email, Webhooks
- **Grouping**: Intelligent alert grouping
- **Inhibition**: Suppress redundant alerts

### Jaeger
- **Distributed Tracing**: Request flow visualization
- **Span Storage**: Trace data persistence
- **Service Topology**: Service dependency mapping
- **Performance Analysis**: Latency and error investigation

## Configuration

### Add Service to Monitoring

1. **Prometheus**: Add job to `prometheus.yml`
   ```yaml
   - job_name: 'new-service'
     static_configs:
       - targets: ['new-service:9090']
   ```

2. **Logstash**: Add pattern and filter to `logstash-pipeline.conf`

3. **Elasticsearch**: Update `elasticsearch-templates.json` if needed

4. **Grafana**: Create new dashboard or update existing

### Add Alert Rule

Edit `prometheus-rules.yml` and add rule:

```yaml
- alert: CustomAlert
  expr: metric > threshold
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "Alert summary"
```

### Customize Retention

**Prometheus**:
- Edit `docker-compose.monitoring.yml`
- Change `--storage.tsdb.retention.time=30d`

**Elasticsearch**:
- Configure ILM policy
- See MONITORING_SETUP.md for details

## Troubleshooting

### Services not scraped
```bash
# Check metrics endpoint
curl http://invoice-service:9090/metrics

# Check Prometheus targets
curl http://localhost:9090/api/v1/targets
```

### No logs appearing
```bash
# Check Logstash
docker logs invoiceshelf-logstash

# Send test log
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"message":"test", "level":"info"}'
```

### High memory usage
- Reduce retention period
- Lower scrape frequency
- Enable compression
- Archive old indices

## Performance Tips

1. **Prometheus**
   - Use recording rules for complex queries
   - Implement query caching
   - Adjust scrape intervals

2. **Elasticsearch**
   - Enable index lifecycle management
   - Use appropriate shard count
   - Optimize refresh intervals

3. **Grafana**
   - Use panel caching
   - Limit query time ranges
   - Optimize dashboard sizes

4. **Logstash**
   - Use multiple workers
   - Implement batch processing
   - Monitor queue depth

## Security

### Credentials to Change

- [ ] Grafana admin password
- [ ] Elasticsearch elastic user password
- [ ] AlertManager webhook URLs
- [ ] PagerDuty service keys
- [ ] Slack webhook URLs

### Data Protection

- Sensitive fields are automatically redacted:
  - Passwords: `***REDACTED***`
  - API keys: `***REDACTED***`
  - Tokens: `***REDACTED***`

### Network Security

- Restrict port access
- Use VPC/private networks
- Enable TLS for production
- Implement RBAC

## Related Documentation

- **MONITORING_SETUP.md**: Complete technical setup guide
- **MONITORING_GUIDE.md**: User guide and troubleshooting
- **MONITORING_IMPLEMENTATION_SUMMARY.md**: Implementation details
- **docker-compose.monitoring.yml**: Service definitions

## Support

For issues or questions:
1. Check MONITORING_GUIDE.md troubleshooting section
2. Review logs: `docker logs <service-name>`
3. Check Prometheus for targets
4. Verify network connectivity

## Monitoring Stack Architecture

```
┌─────────────────────────────────────────────────┐
│          InvoiceShelf Microservices             │
│  (6 services + MySQL + Redis + RabbitMQ + Kong) │
└──────────────────┬──────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
    ┌───▼────┐          ┌─────▼───┐
    │Prometheus│          │Jaeger   │
    │(Metrics) │          │(Traces) │
    └───┬────┘          └─────┬───┘
        │                     │
    ┌───▼──────────────────────▼───┐
    │  Elasticsearch (Logs)        │
    │  ↑                            │
    │  │                            │
    │  Logstash                     │
    │  (Pipeline)                   │
    └────────────┬──────────────────┘
                 │
        ┌────────┴────────────┐
        │                     │
    ┌───▼─────┐          ┌────▼────┐
    │ Kibana  │          │AlertManager│
    │(Logs UI)│          │(Alerts)    │
    └─────────┘          └────┬─────┘
                              │
                        ┌─────▼──────┐
                        │ Grafana    │
                        │(Dashboards)│
                        └──────────┬─┘
                                  │
                        ┌─────────┴──────────┐
                        │                    │
                    ┌───▼──┐            ┌────▼─┐
                    │Slack │            │Pagerduty
                    └──────┘            └────────┘
```

---

**Version**: 1.0
**Last Updated**: 2024-08-30
**Status**: Production-Ready
