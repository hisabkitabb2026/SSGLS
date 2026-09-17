# Kong API Gateway & RabbitMQ Setup Guide

Comprehensive guide for deploying Kong API Gateway, RabbitMQ, and supporting infrastructure for InvoiceShelf.

## Files Created

### Kong Configuration Files

1. **kong.conf** - Main Kong configuration file
   - Database connectivity settings
   - SSL/TLS configuration
   - Performance tuning
   - Plugin configuration
   - Network and DNS settings

2. **kong-services.yaml** - Service definitions
   - 12 microservices defined (Invoice, Estimate, Payment, Customer, Company, Auth, Report, Admin, Settings, File, Health, Web)
   - DDD-based domain routing
   - Connection timeout and retry settings
   - Service tags for organization

3. **kong-routes.yaml** - URL routing rules
   - RESTful endpoint mappings
   - Path-based and regex routing
   - CRUD operation routes for each domain
   - Health check endpoints
   - 40+ individual routes defined

4. **kong-plugins.yaml** - Plugin configurations
   - **JWT Authentication** - Token-based security (HS256, RS256)
   - **Rate Limiting** - 500 req/hour for APIs, 100 req/s for health checks
   - **CORS** - Cross-origin resource sharing for web clients
   - **Proxy Cache** - Response caching with 300-600s TTL
   - **Key Auth** - API key authentication
   - **ACL** - Role-based access control for admin endpoints
   - **Response/Request Transformers** - Header manipulation
   - **Prometheus** - Metrics export
   - **OAuth2** - Social login integration
   - **IP Restriction** - Whitelist for admin endpoints
   - **Bot Detection** - Crawler filtering
   - **Statsd** - Metrics collection

### Docker Compose Files

5. **docker-compose.kong.yml** - Complete Kong stack
   - Kong API Gateway (3.4 Alpine)
   - PostgreSQL 15 (Kong database)
   - Redis 7 (caching & rate limiting)
   - Konga (Kong Admin UI)
   - Prometheus (metrics collection)
   - Grafana (visualization)
   - Jaeger (distributed tracing)
   - Elasticsearch (log storage)
   - Kibana (log analysis)
   - Swagger UI (API documentation)
   - Health check service

6. **docker-compose.rabbitmq.yml** - RabbitMQ messaging stack
   - RabbitMQ 3.12 with management plugin
   - RabbitMQ Exporter (Prometheus metrics)
   - Optional services (profiles):
     - Queue Monitor
     - Dead Letter Queue Handler
     - Message Archive Database

### RabbitMQ Configuration Files

7. **rabbitmq.conf** - RabbitMQ configuration
   - Network listeners (AMQP, MQTT, STOMP)
   - SSL/TLS setup
   - Virtual host configuration
   - Queue master location strategy
   - Memory and disk limits
   - Channel and connection limits
   - Cluster configuration
   - Authentication & authorization

8. **rabbitmq-definitions.json** - Pre-configured RabbitMQ setup
   - Users and virtual hosts
   - 9 topic exchanges:
     - invoice, estimate, payment, customer
     - notification, report, pdf, email
     - dlq (dead letter queue)
   - 12 queues with TTL and DLQ bindings
   - 11 exchange-to-queue bindings
   - High availability policies

### Monitoring & Observability

9. **prometheus.yml** - Prometheus scrape configuration
   - 20+ scrape targets
   - Kong metrics (proxy + admin API)
   - RabbitMQ management API
   - Application health checks
   - Elasticsearch metrics
   - PostgreSQL, Redis monitoring
   - Jaeger tracing
   - Blackbox endpoint monitoring
   - Service discovery configuration

10. **grafana-datasources.yml** - Grafana data source definitions
    - Prometheus (metrics)
    - Elasticsearch (logs)
    - PostgreSQL (direct queries)
    - Jaeger (distributed traces)
    - CloudWatch & Google Cloud Monitoring (optional)
    - InfluxDB, Loki, Graphite (optional)

### UI Files

11. **health-check.html** - Health status dashboard
    - Real-time service status visualization
    - Quick links to all services
    - Network information
    - Auto-refreshing timestamps
    - Responsive mobile design

## Quick Start

### 1. Start Kong Infrastructure

```bash
# Create the invoiceshelf-network if it doesn't exist
docker network create invoiceshelf-network

# Start Kong stack (includes Prometheus, Grafana, Elasticsearch, Kibana, Jaeger)
docker compose -f docker-compose.kong.yml up -d

# Verify Kong is running
docker compose -f docker-compose.kong.yml ps

# Check Kong health
curl http://localhost:8001
```

### 2. Start RabbitMQ

```bash
# Start RabbitMQ (uses existing invoiceshelf-network)
docker compose -f docker-compose.rabbitmq.yml up -d

# Verify RabbitMQ
docker compose -f docker-compose.rabbitmq.yml ps

# Access RabbitMQ Management
# URL: http://localhost:15672
# Username: invoiceshelf
# Password: rabbitmq_password
```

### 3. Configure Kong Services & Routes

Kong configurations are provided as YAML files. Load them using Kong Admin API or Konga:

**Using Kong Admin API:**
```bash
# Load services
curl -X POST http://localhost:8001/services \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "invoice-service",
    "protocol": "http",
    "host": "app",
    "port": 9000,
    "path": "/api/v1/invoices"
  }'

# Load routes
curl -X POST http://localhost:8001/services/invoice-service/routes \
  -H 'Content-Type: application/json' \
  -d '{
    "paths": ["/api/v1/invoices"],
    "methods": ["GET", "POST"]
  }'

# Enable JWT plugin
curl -X POST http://localhost:8001/services/invoice-service/plugins \
  -H 'Content-Type: application/json' \
  -d '{
    "name": "jwt",
    "config": {
      "key_claim_name": "iss",
      "algorithms": ["HS256"]
    }
  }'
```

**Using Konga UI (Recommended):**
1. Access http://localhost:1337
2. Connect to Kong Admin API: `http://kong:8001`
3. Manually add services, routes, and plugins via the UI

## Service Endpoints

### Core Services
- **Kong Proxy**: http://localhost:8000
- **Kong Admin**: http://localhost:8001
- **Kong Admin UI**: http://localhost:8002

### Management & Monitoring
- **Konga (Kong Admin Dashboard)**: http://localhost:1337
- **Grafana (Metrics & Dashboards)**: http://localhost:3000 (admin/admin_password)
- **Prometheus**: http://localhost:9090
- **Jaeger (Distributed Tracing)**: http://localhost:16686

### Logging & Analysis
- **Kibana (Log Analysis)**: http://localhost:5601
- **Elasticsearch**: http://localhost:9200

### Message Queue
- **RabbitMQ Management**: http://localhost:15672 (invoiceshelf/rabbitmq_password)
- **RabbitMQ AMQP**: amqp://localhost:5672
- **RabbitMQ MQTT**: mqtt://localhost:1883

### Documentation
- **Swagger UI**: http://localhost:8004
- **Health Check**: http://localhost:8100

## RabbitMQ Queues & Exchanges

### Exchanges (Topic Type)
- `invoiceshelf.invoice` - Invoice events
- `invoiceshelf.estimate` - Estimate events
- `invoiceshelf.payment` - Payment events
- `invoiceshelf.customer` - Customer events
- `invoiceshelf.notification` - Notifications (email, SMS)
- `invoiceshelf.report` - Report generation
- `invoiceshelf.pdf` - PDF generation
- `invoiceshelf.email` - Email dispatch
- `invoiceshelf.dlq` - Dead Letter Queue

### Queues
- `invoiceshelf.invoice.created` - New invoice events
- `invoiceshelf.invoice.updated` - Invoice updates
- `invoiceshelf.invoice.sent` - Sent invoices
- `invoiceshelf.estimate.created` - New estimates
- `invoiceshelf.estimate.sent` - Sent estimates
- `invoiceshelf.payment.received` - Payment confirmations
- `invoiceshelf.customer.registered` - New customer registrations
- `invoiceshelf.notification.email` - Email notifications
- `invoiceshelf.notification.sms` - SMS notifications
- `invoiceshelf.pdf.generation` - PDF rendering jobs
- `invoiceshelf.report.generation` - Report generation jobs
- `invoiceshelf.dlq.queue` - Dead letter storage

## Kong Plugin Usage Examples

### JWT Authentication
```bash
# Generate JWT token
curl -X POST http://localhost:8001/consumers/admin/jwt \
  -H 'Content-Type: application/json' \
  -d '{}'

# Use token in requests
curl http://localhost:8000/api/v1/invoices \
  -H 'Authorization: Bearer <jwt_token>'
```

### API Key Authentication
```bash
# Create API key
curl -X POST http://localhost:8001/consumers/api-client/key-auth \
  -H 'Content-Type: application/json' \
  -d '{"key": "my-api-key"}'

# Use API key
curl http://localhost:8000/api/v1/invoices?apikey=my-api-key
```

### Rate Limiting
- **Default**: 500 requests per hour per consumer
- **Health checks**: 100 requests per second per IP
- **File uploads**: 2 concurrent requests

### Caching
- **Invoice endpoints**: 300 seconds
- **Reports**: 600 seconds
- Cache varies by `X-Company-ID` header

## Monitoring & Metrics

### Prometheus Targets (http://localhost:9090)
- Kong proxy and admin metrics
- RabbitMQ broker metrics
- Application health
- Infrastructure metrics (CPU, memory, disk)

### Grafana Dashboards (http://localhost:3000)
1. Navigate to Dashboards
2. Pre-configured dashboards available:
   - Kong API Gateway
   - RabbitMQ Metrics
   - Infrastructure Health
   - Application Performance

### Jaeger Tracing (http://localhost:16686)
- Distributed request tracing
- Service dependency visualization
- Performance profiling

## Troubleshooting

### Kong Won't Start
```bash
# Check Kong logs
docker compose -f docker-compose.kong.yml logs kong

# Check database connectivity
docker compose -f docker-compose.kong.yml exec kong kong check

# Restart database migrations
docker compose -f docker-compose.kong.yml restart kong-migrations
```

### RabbitMQ Connection Issues
```bash
# Check RabbitMQ logs
docker compose -f docker-compose.rabbitmq.yml logs rabbitmq

# Verify health
docker compose -f docker-compose.rabbitmq.yml exec rabbitmq rabbitmq-diagnostics status

# List users
docker compose -f docker-compose.rabbitmq.yml exec rabbitmq rabbitmqctl list_users
```

### Prometheus Not Scraping
```bash
# Check Prometheus targets
curl http://localhost:9090/api/v1/targets

# Validate prometheus.yml
docker compose -f docker-compose.kong.yml exec prometheus \
  promtool check config /etc/prometheus/prometheus.yml
```

## Security Considerations

1. **Change Default Passwords**
   - RabbitMQ: Change `rabbitmq_password` in rabbitmq-definitions.json
   - Grafana: Change admin password after first login
   - Kong: Generate and secure admin credentials

2. **SSL/TLS Certificates**
   - Generate certificates for Kong SSL ports (8443, 8444)
   - Configure certificate paths in kong.conf

3. **Network Security**
   - Use private Docker networks
   - Implement firewall rules
   - Use VPN for remote access

4. **API Key Management**
   - Rotate keys regularly
   - Use separate keys per environment
   - Implement key versioning

5. **Database Security**
   - Use strong PostgreSQL passwords
   - Enable SSL for database connections
   - Regular backups

## Performance Tuning

### Kong Configuration
- `worker_processes: auto` - CPU-based worker scaling
- `worker_connections: 10000` - Connection pool size
- `db_pool_size: 30` - Database connection pool

### RabbitMQ Configuration
- `vm_memory_high_watermark.relative: 0.6` - Memory threshold
- `cluster_partition_handling: autoheal` - Automatic recovery
- Quorum queues for high availability

### Redis Configuration
- Used for rate limiting and caching
- Configured with persistence (appendonly: yes)
- Memory limits via Docker resource constraints

## Production Deployment

### Pre-deployment Checklist
- [ ] Update all default passwords
- [ ] Generate and install SSL certificates
- [ ] Configure backup strategies
- [ ] Set up log aggregation
- [ ] Enable monitoring and alerting
- [ ] Load test infrastructure
- [ ] Document runbooks
- [ ] Train operations team

### Scaling Considerations
- **Kong**: Horizontally scalable (add more containers)
- **RabbitMQ**: Clustering for high availability
- **Prometheus**: Consider long-term metrics storage
- **Elasticsearch**: Enable sharding and replicas

## Additional Resources

- Kong Documentation: https://docs.konghq.com
- RabbitMQ Documentation: https://www.rabbitmq.com/documentation.html
- Prometheus Guide: https://prometheus.io/docs/
- Grafana Dashboards: https://grafana.com/grafana/dashboards/

## Support & Troubleshooting

For issues or questions:
1. Check service logs: `docker compose logs <service>`
2. Review configuration files for typos
3. Test connectivity between services
4. Check network configuration
5. Review security and firewall settings
