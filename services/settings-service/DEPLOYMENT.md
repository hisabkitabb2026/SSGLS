# Settings Service Deployment Guide

## Table of Contents
1. [Local Development](#local-development)
2. [Docker Deployment](#docker-deployment)
3. [Kubernetes Deployment](#kubernetes-deployment)
4. [Production Checklist](#production-checklist)
5. [Monitoring & Logging](#monitoring--logging)
6. [Troubleshooting](#troubleshooting)

## Local Development

### Prerequisites
- Go 1.21+
- PostgreSQL 15+
- RabbitMQ 3.12+
- Make

### Setup

```bash
# Clone the repository
cd services/settings-service

# Install dependencies
make deps

# Create .env file
cp .env.example .env

# Edit .env with local credentials
nano .env

# Start services (requires local PostgreSQL and RabbitMQ)
make run
```

## Docker Deployment

### Using Docker Compose (Development)

```bash
# Navigate to service directory
cd services/settings-service

# Create .env file
cp .env.example .env

# Start the entire stack
make docker-up

# Check service status
docker-compose ps

# View logs
docker-compose logs -f settings-service

# Access services
# Settings Service: http://localhost:8005/health
# pgAdmin: http://localhost:5050
# RabbitMQ: http://localhost:15672
# Prometheus: http://localhost:9091
# Grafana: http://localhost:3000
```

### Building Docker Image

```bash
# Build the image
make docker-build

# Or manually
docker build -t invoiceshelf/settings-service:latest .

# Test the image
docker run -p 8005:8005 invoiceshelf/settings-service:latest
```

### Pushing to Registry

```bash
# Login to registry
docker login your-registry

# Tag the image
docker tag invoiceshelf/settings-service:latest your-registry/settings-service:v1.0.0

# Push to registry
docker push your-registry/settings-service:v1.0.0
```

### Docker Compose with External Database

For production, use external PostgreSQL and RabbitMQ:

```yaml
version: '3.8'
services:
  settings-service:
    image: invoiceshelf/settings-service:latest
    container_name: settings-service
    ports:
      - "8005:8005"
    environment:
      PORT: 8005
      ENVIRONMENT: production
      DEBUG: "false"
      DB_HOST: your-postgresql-host
      DB_PORT: 5432
      DB_USER: settings_user
      DB_PASSWORD: secure_password
      DB_NAME: settings_db
      DB_SSL_MODE: require
      JWT_SECRET: your-secure-jwt-secret-key
      RABBITMQ_HOST: your-rabbitmq-host
      RABBITMQ_USER: guest
      RABBITMQ_PASS: guest
      LOG_LEVEL: info
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:8005/health"]
      interval: 30s
      timeout: 10s
      retries: 3
```

## Kubernetes Deployment

### Prerequisites
- Kubernetes 1.24+
- kubectl configured
- Docker image pushed to registry
- PostgreSQL instance accessible from cluster
- RabbitMQ instance accessible from cluster

### Create Namespace

```bash
kubectl create namespace invoiceshelf
```

### Create Secrets

```bash
# Create JWT secret
kubectl create secret generic settings-jwt \
  --from-literal=jwt-secret="your-super-secure-jwt-secret-key-change-in-production" \
  -n invoiceshelf

# Create database credentials
kubectl create secret generic settings-db \
  --from-literal=db-user=settings_user \
  --from-literal=db-password=secure_db_password \
  -n invoiceshelf

# Create RabbitMQ credentials
kubectl create secret generic settings-rabbitmq \
  --from-literal=rabbitmq-user=guest \
  --from-literal=rabbitmq-pass=guest \
  -n invoiceshelf
```

### Create ConfigMap

```bash
kubectl create configmap settings-config \
  --from-literal=environment=production \
  --from-literal=db-host=postgresql.default.svc.cluster.local \
  --from-literal=db-port=5432 \
  --from-literal=db-name=settings_db \
  --from-literal=db-ssl-mode=require \
  --from-literal=rabbitmq-host=rabbitmq.default.svc.cluster.local \
  --from-literal=rabbitmq-port=5672 \
  --from-literal=log-level=info \
  -n invoiceshelf
```

### Create Deployment

```yaml
# settings-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: settings-service
  namespace: invoiceshelf
  labels:
    app: settings-service
    version: v1
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: settings-service
  template:
    metadata:
      labels:
        app: settings-service
      annotations:
        prometheus.io/scrape: "true"
        prometheus.io/port: "9090"
        prometheus.io/path: "/metrics"
    spec:
      serviceAccountName: settings-service
      containers:
      - name: settings-service
        image: your-registry/settings-service:v1.0.0
        imagePullPolicy: IfNotPresent
        ports:
        - name: http
          containerPort: 8005
          protocol: TCP
        - name: metrics
          containerPort: 9090
          protocol: TCP
        env:
        - name: PORT
          value: "8005"
        - name: ENVIRONMENT
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: environment
        - name: DEBUG
          value: "false"
        - name: DB_HOST
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: db-host
        - name: DB_PORT
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: db-port
        - name: DB_USER
          valueFrom:
            secretKeyRef:
              name: settings-db
              key: db-user
        - name: DB_PASSWORD
          valueFrom:
            secretKeyRef:
              name: settings-db
              key: db-password
        - name: DB_NAME
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: db-name
        - name: DB_SSL_MODE
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: db-ssl-mode
        - name: JWT_SECRET
          valueFrom:
            secretKeyRef:
              name: settings-jwt
              key: jwt-secret
        - name: RABBITMQ_HOST
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: rabbitmq-host
        - name: RABBITMQ_PORT
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: rabbitmq-port
        - name: RABBITMQ_USER
          valueFrom:
            secretKeyRef:
              name: settings-rabbitmq
              key: rabbitmq-user
        - name: RABBITMQ_PASS
          valueFrom:
            secretKeyRef:
              name: settings-rabbitmq
              key: rabbitmq-pass
        - name: LOG_LEVEL
          valueFrom:
            configMapKeyRef:
              name: settings-config
              key: log-level
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
          timeoutSeconds: 5
          failureThreshold: 3
        resources:
          requests:
            cpu: 100m
            memory: 128Mi
          limits:
            cpu: 500m
            memory: 512Mi
        securityContext:
          allowPrivilegeEscalation: false
          readOnlyRootFilesystem: true
          runAsNonRoot: true
          capabilities:
            drop:
              - ALL
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
                  - settings-service
              topologyKey: kubernetes.io/hostname
```

### Create Service

```yaml
# settings-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: settings-service
  namespace: invoiceshelf
  labels:
    app: settings-service
spec:
  type: ClusterIP
  ports:
  - name: http
    port: 8005
    targetPort: http
    protocol: TCP
  - name: metrics
    port: 9090
    targetPort: metrics
    protocol: TCP
  selector:
    app: settings-service
```

### Create Service Account (RBAC)

```yaml
# settings-rbac.yaml
apiVersion: v1
kind: ServiceAccount
metadata:
  name: settings-service
  namespace: invoiceshelf

---
apiVersion: rbac.authorization.k8s.io/v1
kind: Role
metadata:
  name: settings-service
  namespace: invoiceshelf
rules:
- apiGroups: [""]
  resources: ["pods"]
  verbs: ["get", "list"]

---
apiVersion: rbac.authorization.k8s.io/v1
kind: RoleBinding
metadata:
  name: settings-service
  namespace: invoiceshelf
roleRef:
  apiGroup: rbac.authorization.k8s.io
  kind: Role
  name: settings-service
subjects:
- kind: ServiceAccount
  name: settings-service
  namespace: invoiceshelf
```

### Deploy to Kubernetes

```bash
# Apply manifests
kubectl apply -f settings-rbac.yaml
kubectl apply -f settings-deployment.yaml
kubectl apply -f settings-service.yaml

# Check deployment status
kubectl get deployment -n invoiceshelf
kubectl get pods -n invoiceshelf
kubectl get svc -n invoiceshelf

# View logs
kubectl logs -n invoiceshelf -f deployment/settings-service

# Port forward for local testing
kubectl port-forward -n invoiceshelf svc/settings-service 8005:8005
```

### Create Ingress (Optional)

```yaml
# settings-ingress.yaml
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: settings-service
  namespace: invoiceshelf
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
    nginx.ingress.kubernetes.io/rate-limit: "100"
spec:
  ingressClassName: nginx
  tls:
  - hosts:
    - settings-api.yourdomain.com
    secretName: settings-tls
  rules:
  - host: settings-api.yourdomain.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: settings-service
            port:
              number: 8005
```

### Create HPA (Horizontal Pod Autoscaler)

```yaml
# settings-hpa.yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: settings-service
  namespace: invoiceshelf
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: settings-service
  minReplicas: 3
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
```

## Production Checklist

- [ ] Change JWT secret to a strong random value
- [ ] Use external PostgreSQL with backups enabled
- [ ] Use external RabbitMQ with clustering
- [ ] Enable SSL/TLS for database connections
- [ ] Configure HTTPS/TLS for API endpoints
- [ ] Set `ENVIRONMENT=production` and `DEBUG=false`
- [ ] Configure log aggregation (ELK, Datadog, etc.)
- [ ] Set up Prometheus and Grafana monitoring
- [ ] Configure AlertManager for alerts
- [ ] Enable database encryption at rest
- [ ] Implement API rate limiting at gateway level
- [ ] Set up automated backups for PostgreSQL
- [ ] Configure log retention policies
- [ ] Run security scanning on Docker images
- [ ] Document all configuration and secrets management
- [ ] Test disaster recovery procedures
- [ ] Set up CI/CD pipeline
- [ ] Plan capacity and performance testing

## Monitoring & Logging

### Prometheus Setup

```yaml
# Add to prometheus.yml
scrape_configs:
  - job_name: 'settings-service'
    static_configs:
      - targets: ['settings-service:9090']
    metrics_path: '/metrics'
```

### Grafana Dashboards

Import dashboard JSON from Grafana Cloud or create custom dashboards for:
- Request latency
- Error rates
- Database connection pool
- RabbitMQ metrics

### Log Aggregation

Send logs to ELK, Datadog, CloudWatch, etc.:

```bash
# With Docker
docker-compose logs settings-service | \
  curl -X POST http://logstash:5000 \
  -H "Content-Type: application/json" \
  -d @-
```

## Troubleshooting

### Service won't start

```bash
# Check logs
docker-compose logs settings-service
# or
kubectl logs -n invoiceshelf deployment/settings-service

# Check database connectivity
telnet postgres 5432
# or
kubectl exec -it pod/settings-service -- \
  curl postgres:5432
```

### High memory usage

- Check for memory leaks: `go tool pprof`
- Adjust GOGC environment variable
- Increase Pod memory limits

### Database connection failures

- Verify DB_SSL_MODE setting
- Check PostgreSQL max connections
- Monitor connection pool: `/metrics` endpoint

### RabbitMQ connection failures

- Verify RABBITMQ_HOST and credentials
- Check RabbitMQ logs
- Ensure exchange is declared

For more details, see README.md and API.md
