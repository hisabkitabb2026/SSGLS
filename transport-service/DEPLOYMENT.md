# Deployment Guide - Transport Microservice

## Prerequisites

- Docker & Docker Compose
- Python 3.11+ (for local development)
- PostgreSQL 15 (or use Docker)
- RabbitMQ 3.12 (or use Docker)

## Local Development Deployment

### 1. Setup Environment

```bash
cd transport-service

# Copy environment template
cp .env.example .env

# Create virtual environment
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

### 2. Install Dependencies

```bash
pip install -r requirements.txt
```

### 3. Database Setup

For PostgreSQL on your machine:

```bash
# Create database and user
createdb -U postgres transport_db
createuser -U postgres -d transport_user
psql -U postgres -d transport_db -c "ALTER USER transport_user WITH PASSWORD 'transport_password';"
psql -U postgres -d transport_db -f scripts/init-db.sql
```

Or use Docker:

```bash
docker run -d \
  --name transport-postgres \
  -e POSTGRES_DB=transport_db \
  -e POSTGRES_USER=transport_user \
  -e POSTGRES_PASSWORD=transport_password \
  -p 5432:5432 \
  postgres:15-alpine
```

### 4. Configure .env

Update `.env` with your database connection:

```env
DATABASE_URL=postgresql://transport_user:transport_password@localhost:5432/transport_db
JWT_SECRET_KEY=your-dev-secret-key-min-32-chars
```

### 5. Run Application

```bash
# Development with auto-reload
python -m uvicorn app.main:app --reload --port 8006

# Or using make
make dev
```

Access at: http://localhost:8006/api/docs

## Docker Deployment

### Quick Start with Docker Compose

```bash
# Start all services
docker-compose up -d

# Verify services
docker-compose ps

# View logs
docker-compose logs -f transport-api
```

### Services Available

- **API**: http://localhost:8006
- **Docs**: http://localhost:8006/api/docs
- **RabbitMQ UI**: http://localhost:15672 (guest/guest)
- **pgAdmin**: http://localhost:5050 (admin@example.com/admin)

### Stop Services

```bash
# Stop containers
docker-compose down

# Remove volumes (careful!)
docker-compose down -v
```

## Building Docker Image

### Build Image

```bash
docker build -t transport-service:1.0.0 .

# With build args
docker build -t transport-service:1.0.0 \
  --build-arg VERSION=1.0.0 \
  .
```

### Run Container

```bash
docker run -d \
  --name transport-api \
  -p 8006:8006 \
  -e DATABASE_URL=postgresql://... \
  -e JWT_SECRET_KEY=your-secret \
  -e RABBITMQ_HOST=host.docker.internal \
  transport-service:1.0.0
```

## Production Deployment

### Environment Configuration

Create `.env.production`:

```env
APP_NAME=Transport Microservice
APP_VERSION=1.0.0
DEBUG=false
HOST=0.0.0.0
PORT=8006

# Secure database URL
DATABASE_URL=postgresql://prod_user:strong_password@db.example.com:5432/transport_prod
DATABASE_ECHO=false

# Strong JWT secret (minimum 32 characters)
JWT_SECRET_KEY=your-very-long-random-secret-key-at-least-32-characters
JWT_ALGORITHM=HS256
JWT_EXPIRATION_HOURS=24

# RabbitMQ
RABBITMQ_HOST=rabbitmq.example.com
RABBITMQ_PORT=5672
RABBITMQ_USER=rabbitmq_user
RABBITMQ_PASSWORD=strong_password
RABBITMQ_VHOST=/transport

# Logging
LOG_LEVEL=WARNING
LOG_FORMAT=json

# Metrics
METRICS_ENABLED=true
```

### Database Initialization (Production)

```bash
# Using psql
psql -h db.example.com -U admin -d postgres -f scripts/init-db.sql

# Or with migration tools (if applicable)
python -m alembic upgrade head
```

### Security Checklist

- [ ] JWT_SECRET_KEY is strong (32+ characters, random)
- [ ] Database password is strong
- [ ] RabbitMQ credentials are strong
- [ ] CORS origins are restricted
- [ ] DEBUG is set to false
- [ ] SSL/TLS is enabled for database
- [ ] Database backups are configured
- [ ] RabbitMQ message persistence is enabled
- [ ] Application logs are aggregated
- [ ] Health checks are monitored

### Kubernetes Deployment

Create `k8s/deployment.yaml`:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: transport-api
  labels:
    app: transport-api
    version: v1
spec:
  replicas: 3
  selector:
    matchLabels:
      app: transport-api
  template:
    metadata:
      labels:
        app: transport-api
    spec:
      containers:
      - name: transport-api
        image: transport-service:1.0.0
        imagePullPolicy: Always
        ports:
        - containerPort: 8006
          name: http
        env:
        - name: DATABASE_URL
          valueFrom:
            secretKeyRef:
              name: transport-secrets
              key: database-url
        - name: JWT_SECRET_KEY
          valueFrom:
            secretKeyRef:
              name: transport-secrets
              key: jwt-secret
        - name: RABBITMQ_HOST
          valueFrom:
            configMapKeyRef:
              name: transport-config
              key: rabbitmq-host
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 8006
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 8006
          initialDelaySeconds: 20
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: transport-api-service
spec:
  type: LoadBalancer
  ports:
  - port: 80
    targetPort: 8006
    protocol: TCP
  selector:
    app: transport-api
```

Create secrets and config:

```bash
kubectl create secret generic transport-secrets \
  --from-literal=database-url='postgresql://...' \
  --from-literal=jwt-secret='your-secret'

kubectl create configmap transport-config \
  --from-literal=rabbitmq-host='rabbitmq.default.svc.cluster.local'

kubectl apply -f k8s/deployment.yaml
```

### Monitoring & Logging

#### Prometheus Scrape Config

```yaml
scrape_configs:
  - job_name: 'transport-api'
    static_configs:
      - targets: ['localhost:8006']
    metrics_path: '/metrics'
    scrape_interval: 30s
```

#### Alerting Rules

```yaml
groups:
  - name: transport_api
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status_code=~"5.."}[5m]) > 0.05
        for: 5m

      - alert: DatabaseUnhealthy
        expr: transport_health_check{component="database"} == 0
        for: 2m

      - alert: RabbitMQUnhealthy
        expr: transport_health_check{component="rabbitmq"} == 0
        for: 2m
```

#### Log Aggregation (ELK Stack)

```bash
# Filebeat config
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /app/logs/*.log
  json.message_key: message
  json.keys_under_root: true

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
```

## Testing Deployment

### Run Tests

```bash
# All tests
pytest --cov=app --cov=config

# Only fast tests
pytest -m "not slow"

# Specific test file
pytest tests/integration/test_transport_routes.py -v
```

### Health Checks

```bash
# API Health
curl http://localhost:8006/health

# Metrics
curl http://localhost:8006/metrics | head -20

# Full healthcheck response
curl -s http://localhost:8006/health | jq .
```

### Load Testing

Using Apache Bench:

```bash
ab -n 1000 -c 10 -H "Authorization: Bearer <token>" http://localhost:8006/api/v1/transports
```

Using wrk:

```bash
wrk -t4 -c100 -d30s -H "Authorization: Bearer <token>" http://localhost:8006/api/v1/transports
```

## Troubleshooting

### Connection Issues

```bash
# Test database connection
psql -h localhost -U transport_user -d transport_db -c "SELECT 1;"

# Test RabbitMQ connection
amqp-declare-queue.py -u amqp://guest:guest@localhost//

# View service logs
docker-compose logs -f transport-api
```

### Performance Issues

```bash
# Check metrics
curl -s http://localhost:8006/metrics | grep -E "^(http_request|transport)" | head -20

# Database query performance
psql -h localhost -U transport_user -d transport_db -c "EXPLAIN ANALYZE SELECT * FROM transports LIMIT 10;"

# Memory usage
docker stats transport-api
```

## Scaling

### Horizontal Scaling

```bash
# Docker Compose with multiple instances
docker-compose up -d --scale transport-api=3

# Load balancer configuration (nginx)
upstream transport {
    server localhost:8006;
    server localhost:8007;
    server localhost:8008;
}
```

### Caching Layer

Consider adding Redis for caching:

```yaml
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    networks:
      - transport-network
```

## Rollback Procedure

```bash
# Keep previous image tags
docker tag transport-service:1.0.0 transport-service:1.0.0-backup

# In case of issues, revert
docker-compose down
# Update docker-compose.yml to use previous image
docker-compose up -d

# Or with Kubernetes
kubectl rollout undo deployment/transport-api
```

## Support

For deployment issues:
1. Check logs: `docker-compose logs -f`
2. Health status: `curl http://localhost:8006/health`
3. Database status: `psql -h localhost -U transport_user -d transport_db -c "SELECT 1;"`
4. RabbitMQ status: Visit http://localhost:15672
