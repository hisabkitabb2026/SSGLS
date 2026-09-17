# Product Service Deployment Guide

Complete deployment instructions for the Product Service microservice.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose 1.29+
- Kubernetes 1.20+ (for K8s deployment)
- PostgreSQL 15+ (for standalone deployment)
- RabbitMQ 3.12+ (for standalone deployment)

## Local Development Deployment

### 1. Quick Start with Docker Compose

```bash
# Clone repository and navigate
cd services/product-service

# Copy environment file
cp .env.example .env

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Check health
curl http://localhost:8003/health
```

### 2. Access Services

- **Product Service**: http://localhost:8003
- **Metrics**: http://localhost:9090/metrics
- **pgAdmin**: http://localhost:5050 (optional profile)
- **Health Check**: http://localhost:8003/health

### 3. Run Tests

```bash
# Run all tests
make test

# Generate coverage report
make test-coverage

# Run with race detector
make test-race
```

## Standalone Deployment

For deployment without the included docker-compose stack.

### 1. Database Setup

```bash
# PostgreSQL connection string
postgresql://product_service:product_password@localhost:5432/product_service

# Create database and user
psql -U postgres << EOF
CREATE USER product_service WITH PASSWORD 'product_password';
CREATE DATABASE product_service OWNER product_service;
ALTER DATABASE product_service OWNER TO product_service;
EOF
```

### 2. RabbitMQ Setup

```bash
# If using Docker
docker run -d \
  --name rabbitmq \
  -e RABBITMQ_DEFAULT_USER=invoiceshelf \
  -e RABBITMQ_DEFAULT_PASS=rabbitmq_password \
  -e RABBITMQ_DEFAULT_VHOST=/invoiceshelf \
  -p 5672:5672 \
  -p 15672:15672 \
  rabbitmq:3.12-management-alpine

# Create vhost
docker exec rabbitmq rabbitmqctl add_vhost /invoiceshelf
docker exec rabbitmq rabbitmqctl set_permissions -p /invoiceshelf invoiceshelf ".*" ".*" ".*"
```

### 3. Build and Run

```bash
# Build the binary
make build

# Configure environment
cp .env.example .env
# Edit .env with your database and RabbitMQ settings

# Run the service
./product-service
```

## Docker Deployment

### Build Image

```bash
# Build image
make docker-build

# Or manually
docker build -t product-service:latest .

# Run container
docker run -d \
  --name product-service \
  -p 8003:8003 \
  -p 9090:9090 \
  --env-file .env \
  --network invoiceshelf-network \
  product-service:latest
```

### Environment Configuration

```bash
# Create .env file
cat > .env << EOF
PORT=8003
APP_ENV=production
LOG_LEVEL=info

DB_HOST=postgres
DB_PORT=5432
DB_USER=product_service
DB_PASSWORD=secure_password
DB_NAME=product_service
DB_SSL_MODE=require

JWT_SECRET=your-very-secure-jwt-secret-key
JWT_EXPIRATION=86400

RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=invoiceshelf
RABBITMQ_PASSWORD=secure_rabbitmq_password
RABBITMQ_VHOST=/invoiceshelf

METRICS_PORT=9090
EOF
```

## Kubernetes Deployment

### 1. Create ConfigMap

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: product-service-config
  namespace: default
data:
  APP_ENV: "production"
  LOG_LEVEL: "info"
  DB_HOST: "postgres.default.svc.cluster.local"
  DB_PORT: "5432"
  DB_USER: "product_service"
  DB_NAME: "product_service"
  RABBITMQ_HOST: "rabbitmq.default.svc.cluster.local"
  RABBITMQ_PORT: "5672"
  RABBITMQ_USER: "invoiceshelf"
  RABBITMQ_VHOST: "/invoiceshelf"
  METRICS_PORT: "9090"
```

### 2. Create Secret

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: product-service-secret
  namespace: default
type: Opaque
stringData:
  DB_PASSWORD: "secure_db_password"
  JWT_SECRET: "secure_jwt_secret_key"
  RABBITMQ_PASSWORD: "secure_rabbitmq_password"
```

### 3. Create Deployment

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: product-service
  namespace: default
  labels:
    app: product-service
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: product-service
  template:
    metadata:
      labels:
        app: product-service
      annotations:
        prometheus.io/scrape: "true"
        prometheus.io/port: "9090"
        prometheus.io/path: "/metrics"
    spec:
      serviceAccountName: product-service
      securityContext:
        runAsNonRoot: true
        runAsUser: 1000
      containers:
      - name: product-service
        image: product-service:latest
        imagePullPolicy: Always
        ports:
        - name: http
          containerPort: 8003
          protocol: TCP
        - name: metrics
          containerPort: 9090
          protocol: TCP
        envFrom:
        - configMapRef:
            name: product-service-config
        - secretRef:
            name: product-service-secret
        livenessProbe:
          httpGet:
            path: /health
            port: http
          initialDelaySeconds: 10
          periodSeconds: 30
          timeoutSeconds: 5
          failureThreshold: 3
        readinessProbe:
          httpGet:
            path: /ready
            port: http
          initialDelaySeconds: 5
          periodSeconds: 10
          timeoutSeconds: 3
          failureThreshold: 2
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          capabilities:
            drop:
            - ALL
        volumeMounts:
        - name: tmp
          mountPath: /tmp
      volumes:
      - name: tmp
        emptyDir: {}
      affinity:
        podAntiAffinity:
          preferredDuringSchedulingIgnoredDuringExecution:
          - weight: 100
            podAffinityTerm:
              labelSelector:
                matchExpressions:
                - key: app
                  operator: In
                  values:
                  - product-service
              topologyKey: kubernetes.io/hostname
```

### 4. Create Service

```yaml
apiVersion: v1
kind: Service
metadata:
  name: product-service
  namespace: default
  labels:
    app: product-service
spec:
  type: ClusterIP
  ports:
  - name: http
    port: 8003
    targetPort: http
    protocol: TCP
  - name: metrics
    port: 9090
    targetPort: metrics
    protocol: TCP
  selector:
    app: product-service
```

### 5. Deploy to Kubernetes

```bash
# Apply resources
kubectl apply -f configmap.yaml
kubectl apply -f secret.yaml
kubectl apply -f deployment.yaml
kubectl apply -f service.yaml

# Verify deployment
kubectl get deployment product-service
kubectl get pods -l app=product-service
kubectl get svc product-service

# Check logs
kubectl logs -l app=product-service -f

# Port forward for testing
kubectl port-forward svc/product-service 8003:8003
```

## Scaling

### Horizontal Scaling

```bash
# Scale to 5 replicas
kubectl scale deployment product-service --replicas=5

# Or use Horizontal Pod Autoscaler
kubectl autoscale deployment product-service --min=3 --max=10 --cpu-percent=80
```

### Load Balancing

```yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: product-service-ingress
  namespace: default
spec:
  ingressClassName: nginx
  rules:
  - host: products.example.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: product-service
            port:
              number: 8003
```

## Monitoring

### Prometheus Setup

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: prometheus-config
data:
  prometheus.yml: |
    global:
      scrape_interval: 15s
    scrape_configs:
    - job_name: 'product-service'
      kubernetes_sd_configs:
      - role: pod
      relabel_configs:
      - source_labels: [__meta_kubernetes_pod_label_app]
        action: keep
        regex: product-service
```

### Grafana Dashboard

```json
{
  "dashboard": {
    "title": "Product Service Metrics",
    "panels": [
      {
        "title": "Request Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total[5m])"
          }
        ]
      },
      {
        "title": "Error Rate",
        "targets": [
          {
            "expr": "rate(http_requests_total{status=~'5..'}[5m])"
          }
        ]
      },
      {
        "title": "Response Time",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, http_request_duration_seconds_bucket)"
          }
        ]
      }
    ]
  }
}
```

## Health Checks

### Before Going to Production

```bash
# 1. Health check
curl -v http://localhost:8003/health

# 2. Readiness check
curl -v http://localhost:8003/ready

# 3. Create a product
curl -X POST http://localhost:8003/api/v1/products \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Product",
    "sku": "TEST-001",
    "unit_price": 99.99
  }'

# 4. List products
curl http://localhost:8003/api/v1/products \
  -H "Authorization: Bearer <token>"

# 5. Check metrics
curl http://localhost:9090/metrics
```

## Troubleshooting

### Service Won't Start

```bash
# Check logs
docker-compose logs product-service

# Verify database connectivity
docker-compose exec postgres psql -U product_service -d product_service -c "SELECT 1;"

# Check RabbitMQ connectivity
docker-compose exec product-service telnet rabbitmq 5672
```

### Database Issues

```bash
# Check migrations
docker-compose exec product-service go run . migrate

# Reset database (development only)
docker-compose exec postgres psql -U product_service -d product_service << 'EOF'
DROP TABLE IF EXISTS products;
EOF
```

### Memory Leaks

```bash
# Profile memory usage
go tool pprof http://localhost:6060/debug/pprof/heap

# Check goroutine count
curl http://localhost:6060/debug/pprof/goroutine
```

## Backup & Recovery

### Database Backup

```bash
# PostgreSQL backup
docker-compose exec postgres pg_dump -U product_service product_service > backup.sql

# Restore
docker-compose exec -T postgres psql -U product_service product_service < backup.sql
```

### RabbitMQ Definitions Export

```bash
# Export RabbitMQ definitions
curl -u invoiceshelf:rabbitmq_password http://localhost:15672/api/definitions > rabbitmq-defs.json

# Import RabbitMQ definitions
curl -u invoiceshelf:rabbitmq_password -X POST http://localhost:15672/api/definitions \
  -H "Content-Type: application/json" \
  -d @rabbitmq-defs.json
```

## Security Checklist

- [ ] Change default JWT_SECRET
- [ ] Change database password
- [ ] Change RabbitMQ password
- [ ] Enable database SSL (DB_SSL_MODE=require)
- [ ] Use HTTPS for API endpoints
- [ ] Implement rate limiting
- [ ] Configure CORS properly
- [ ] Use network policies in Kubernetes
- [ ] Regular security updates for dependencies
- [ ] Enable audit logging

## Performance Tuning

### Database Connection Pool
```env
DB_MAX_IDLE_CONNS=20
DB_MAX_OPEN_CONNS=100
DB_CONN_MAX_LIFETIME=3600
```

### Caching Strategy
- Implement Redis for product caching
- Cache low-stock check results
- Cache SKU lookup results

### Query Optimization
- Add database indexes for frequently queried fields
- Use pagination for list endpoints
- Implement query result caching

## Rollback Procedure

```bash
# Kubernetes rollback
kubectl rollout history deployment/product-service
kubectl rollout undo deployment/product-service --to-revision=<n>

# Docker rollback
docker pull product-service:previous-tag
docker-compose up -d
```
