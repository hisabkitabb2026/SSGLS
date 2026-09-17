# Deployment Guide

Production deployment guide for the Expense Microservice.

## Pre-Deployment Checklist

- [ ] Set strong `JWT_SECRET_KEY` in production `.env`
- [ ] Configure PostgreSQL with proper backups
- [ ] Set up RabbitMQ with persistent storage
- [ ] Configure reverse proxy (nginx/Apache)
- [ ] Set up TLS/SSL certificates
- [ ] Configure logging aggregation (ELK, DataDog, etc.)
- [ ] Set up monitoring and alerting
- [ ] Configure API rate limiting
- [ ] Enable CORS for frontend domain only
- [ ] Set up database connection pooling
- [ ] Configure Sentry for error tracking

## Docker Deployment

### Building Image

```bash
# Production build
docker build -t invoiceshelf/expense-service:1.0.0 .

# With build args
docker build \
  --build-arg PYTHON_VERSION=3.11 \
  -t invoiceshelf/expense-service:1.0.0 .

# Push to registry
docker push invoiceshelf/expense-service:1.0.0
```

### Running Container

```bash
docker run \
  --name expense-service \
  --env-file .env.production \
  -p 8002:8002 \
  -v /var/log/expense-service:/app/logs \
  -d invoiceshelf/expense-service:1.0.0
```

Environment variables (set in `.env.production`):
```bash
SERVICE_ENV=production
DEBUG=False
DATABASE_URL=postgresql://user:pass@postgres-host:5432/expense_service
JWT_SECRET_KEY=<generated-secret>
RABBITMQ_HOST=rabbitmq-host
SENTRY_DSN=https://key@sentry.io/project
LOG_LEVEL=INFO
```

## Kubernetes Deployment

### Deployment Manifest

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: expense-service
  namespace: invoiceshelf
spec:
  replicas: 3
  selector:
    matchLabels:
      app: expense-service
  template:
    metadata:
      labels:
        app: expense-service
    spec:
      containers:
      - name: expense-service
        image: invoiceshelf/expense-service:1.0.0
        imagePullPolicy: IfNotPresent
        ports:
        - containerPort: 8002
          name: api
        - containerPort: 8003
          name: metrics

        env:
        - name: SERVICE_ENV
          value: "production"
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: expense-service-secrets
              key: database-url
        - name: JWT_SECRET_KEY
          valueFrom:
            secretKeyRef:
              name: expense-service-secrets
              key: jwt-secret
        - name: RABBITMQ_HOST
          value: "rabbitmq.invoiceshelf.svc.cluster.local"
        - name: LOG_LEVEL
          value: "INFO"

        resources:
          requests:
            cpu: 250m
            memory: 512Mi
          limits:
            cpu: 500m
            memory: 1024Mi

        livenessProbe:
          httpGet:
            path: /health
            port: 8002
          initialDelaySeconds: 30
          periodSeconds: 10
          timeoutSeconds: 5
          failureThreshold: 3

        readinessProbe:
          httpGet:
            path: /health
            port: 8002
          initialDelaySeconds: 10
          periodSeconds: 5
          timeoutSeconds: 3
          failureThreshold: 3

        securityContext:
          runAsNonRoot: true
          runAsUser: 1000
          readOnlyRootFilesystem: true
          allowPrivilegeEscalation: false
```

### Service

```yaml
apiVersion: v1
kind: Service
metadata:
  name: expense-service
  namespace: invoiceshelf
spec:
  type: ClusterIP
  selector:
    app: expense-service
  ports:
  - port: 8002
    targetPort: 8002
    name: api
  - port: 8003
    targetPort: 8003
    name: metrics
```

### Secrets

```bash
kubectl create secret generic expense-service-secrets \
  --from-literal=database-url='postgresql://user:pass@postgres:5432/expense_service' \
  --from-literal=jwt-secret='<generated-secret>' \
  -n invoiceshelf
```

### Ingress

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: expense-service-ingress
  namespace: invoiceshelf
  annotations:
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
    nginx.ingress.kubernetes.io/rate-limit: "100"
spec:
  ingressClassName: nginx
  tls:
  - hosts:
    - expense-api.invoiceshelf.com
    secretName: expense-service-tls
  rules:
  - host: expense-api.invoiceshelf.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: expense-service
            port:
              number: 8002
```

## Database Setup

### PostgreSQL Installation

```bash
# Using docker
docker run -d \
  --name postgres \
  -e POSTGRES_DB=expense_service \
  -e POSTGRES_USER=expense_user \
  -e POSTGRES_PASSWORD=<strong-password> \
  -v postgres_data:/var/lib/postgresql/data \
  -p 5432:5432 \
  postgres:16-alpine

# Initialize schema
python -c "
from app.models import DatabaseManager
from app.config import get_settings
settings = get_settings()
DatabaseManager.initialize(settings.database_url)
DatabaseManager.create_all()
"
```

### Backup Strategy

```bash
# Daily backups
0 2 * * * pg_dump -U expense_user -h localhost expense_service | \
  gzip > /backups/expense_db_$(date +\%Y\%m\%d).sql.gz

# AWS S3 backup
aws s3 cp /backups/expense_db_*.sql.gz \
  s3://company-backups/databases/ --region us-east-1
```

## RabbitMQ Setup

### Docker

```bash
docker run -d \
  --name rabbitmq \
  -e RABBITMQ_DEFAULT_USER=admin \
  -e RABBITMQ_DEFAULT_PASS=<strong-password> \
  -p 5672:5672 \
  -p 15672:15672 \
  -v rabbitmq_data:/var/lib/rabbitmq \
  rabbitmq:3.13-management-alpine
```

### Production Configuration

```ini
# /etc/rabbitmq/rabbitmq.conf
vm_memory_high_watermark.relative = 0.6
channel_max = 2048
heartbeat = 60
```

## Monitoring & Logging

### Prometheus Scrape Config

```yaml
scrape_configs:
  - job_name: 'expense-service'
    static_configs:
      - targets: ['localhost:8003']
    scrape_interval: 15s
```

### ELK Stack Setup

```yaml
# filebeat.yml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/log/expense-service/*.log
  json.message_key: message
  json.keys_under_root: true

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
  index: "expense-service-%{+yyyy.MM.dd}"
```

### Sentry Configuration

```bash
# Install sentry-sdk
pip install sentry-sdk

# Configure in app
import sentry_sdk
sentry_sdk.init(
    dsn="https://key@sentry.io/project",
    environment="production",
    traces_sample_rate=0.1,
    profiles_sample_rate=0.1,
)
```

## Scaling

### Horizontal Scaling

1. **Multiple Instances**
   ```bash
   docker-compose up --scale expense-service=3
   ```

2. **Load Balancing** - Use nginx/HAProxy:
   ```nginx
   upstream expense_backend {
       server localhost:8002;
       server localhost:8003;
       server localhost:8004;
   }
   ```

### Database Connection Pooling

```python
# In config.py
DATABASE_POOL_SIZE = 50      # Increase from 20
DATABASE_MAX_OVERFLOW = 100   # Increase from 40
```

### Caching Strategy

Consider adding Redis for frequently accessed data:
```python
from redis import Redis
cache = Redis(host='redis-host', port=6379, db=0)
```

## Security Hardening

### Network Policies

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: expense-service-netpol
spec:
  podSelector:
    matchLabels:
      app: expense-service
  policyTypes:
  - Ingress
  - Egress
  ingress:
  - from:
    - namespaceSelector:
        matchLabels:
          name: ingress-nginx
    ports:
    - protocol: TCP
      port: 8002
```

### Pod Security Policy

```yaml
apiVersion: policy/v1beta1
kind: PodSecurityPolicy
metadata:
  name: expense-service-psp
spec:
  privileged: false
  allowPrivilegeEscalation: false
  requiredDropCapabilities:
    - ALL
  runAsUser:
    rule: 'MustRunAsNonRoot'
  fsGroup:
    rule: 'RunAsAny'
  readOnlyRootFilesystem: true
```

## Health Monitoring

### Alerts

Create alerts for:
- Service down: `/health` returns error
- High error rate: > 5% 5xx errors in 5 minutes
- Database connection issues
- RabbitMQ disconnection
- High response time: > 1000ms p99

### Metrics to Monitor

```promql
# Request rate
rate(service_requests_total[5m])

# Error rate
rate(service_errors_total[5m]) / rate(service_requests_total[5m])

# Response time
histogram_quantile(0.99, service_request_duration_seconds)

# Database connections
pg_stat_activity_count
```

## Rollback Procedure

```bash
# Check current version
kubectl rollout history deployment expense-service -n invoiceshelf

# Rollback to previous version
kubectl rollout undo deployment expense-service -n invoiceshelf

# Rollback to specific revision
kubectl rollout undo deployment expense-service --to-revision=2 -n invoiceshelf

# Check rollout status
kubectl rollout status deployment expense-service -n invoiceshelf
```

## Update Procedure

```bash
# 1. Update image
docker build -t invoiceshelf/expense-service:1.0.1 .
docker push invoiceshelf/expense-service:1.0.1

# 2. Update deployment
kubectl set image deployment/expense-service \
  expense-service=invoiceshelf/expense-service:1.0.1 \
  -n invoiceshelf

# 3. Monitor rollout
kubectl rollout status deployment expense-service -n invoiceshelf

# 4. Verify health
kubectl logs -f deployment/expense-service -n invoiceshelf
kubectl get pods -n invoiceshelf
```

## Support & Troubleshooting

### Common Issues

**High Memory Usage**
```bash
# Check memory
docker stats expense-service

# Increase limits in deployment
resources.limits.memory: "2Gi"
```

**Database Connection Timeout**
```python
# Increase timeout in config
SQLALCHEMY_ENGINE_OPTIONS = {
    "pool_pre_ping": True,
    "pool_recycle": 3600,
}
```

**RabbitMQ Queue Buildup**
```bash
# Check queue lengths
rabbitmqctl list_queues name messages

# Restart consumer
docker restart expense-service
```

## Performance Tuning

### Database Optimization

```sql
-- Analyze query performance
EXPLAIN ANALYZE SELECT * FROM expenses WHERE company_id = 1;

-- Create additional indexes
CREATE INDEX idx_expenses_updated_at ON expenses(updated_at);
CREATE INDEX idx_expenses_company_created ON expenses(company_id, created_at);
```

### Application Optimization

```python
# Enable query caching
from functools import lru_cache

@lru_cache(maxsize=1000)
def get_category_breakdown(company_id):
    pass

# Use connection pooling
DATABASE_POOL_SIZE = 100
```
