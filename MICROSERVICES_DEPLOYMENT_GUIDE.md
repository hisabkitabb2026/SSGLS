# Microservices Deployment Guide

## Overview

This guide covers deploying the InvoiceShelf microservices stack using Docker Compose.
The stack consists of 6 domain microservices, PostgreSQL, Redis, RabbitMQ, Kong API Gateway,
and Prometheus/Grafana monitoring.

## Architecture

```
                    ┌──────────────────────────────────┐
                    │         Kong API Gateway          │
                    │         (Port 8000)               │
                    └──────────────┬───────────────────┘
                                   │
          ┌────────────┬───────────┼───────────┬────────────┐
          ▼            ▼           ▼           ▼            ▼
   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
   │ Invoice  │ │ Expense  │ │ Product  │ │ Customer │ │ Settings │
   │ Service  │ │ Service  │ │ Service  │ │ Service  │ │ Service  │
   │ (Node)   │ │ (Python) │ │ (Go)     │ │ (Node)   │ │ (Go)     │
   │ :8010    │ │ :8020    │ │ :8030    │ │ :8040    │ │ :8050    │
   └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘
        │            │            │            │            │
        └────────────┴────────────┼────────────┴────────────┘
                                   │
                    ┌──────────────┼──────────────┐
                    ▼              ▼              ▼
              ┌──────────┐  ┌──────────┐  ┌──────────┐
              │PostgreSQL│  │  Redis   │  │ RabbitMQ │
              │  :5432   │  │  :6379   │  │  :5672   │
              └──────────┘  └──────────┘  └──────────┘
```

## Services

| Service         | Language  | Port | DB              | Description                    |
|-----------------|-----------|------|-----------------|--------------------------------|
| invoice-service | Node.js   | 8010 | invoicing_db    | Invoice & billing operations   |
| expense-service | Python    | 8020 | expense_service | Expense tracking               |
| product-service | Go        | 8030 | product_service | Product catalog management     |
| customer-service| Node.js   | 8040 | customer_service| Customer portal & profiles     |
| settings-service| Go        | 8050 | settings_db     | Configuration management       |
| transport-service| Python   | 8060 | transport_db    | Transport & logistics operations|

## Prerequisites

- Docker Engine 24.0+
- Docker Compose v2.20+
- 4GB+ RAM available for containers

## Quick Start

```bash
# 1. Clone the repository
git clone <repo-url>
cd HackathonIdea

# 2. Copy environment file (defaults are provided)
cp .env .env  # Already exists with defaults

# 3. Build and start all services
docker compose -f docker-compose.full.yml up -d --build

# 4. Check service health
docker compose -f docker-compose.full.yml ps

# 5. View logs
docker compose -f docker-compose.full.yml logs -f
```

## Port Map

| Host Port | Service              | Description                |
|-----------|---------------------|----------------------------|
| 8000      | Kong Proxy          | Public API entry point     |
| 8001      | Kong Admin API      | Kong administration        |
| 8002      | Kong Admin GUI       | Konga dashboard             |
| 8010      | Invoice Service     | Direct service access       |
| 8020      | Expense Service     | Direct service access       |
| 8030      | Product Service     | Direct service access       |
| 8040      | Customer Service    | Direct service access       |
| 8050      | Settings Service    | Direct service access       |
| 8060      | Transport Service   | Direct service access       |
| 5432      | PostgreSQL           | Database                    |
| 6379      | Redis               | Cache & rate limiting       |
| 5672      | RabbitMQ AMQP        | Message broker              |
| 15672     | RabbitMQ Mgmt UI    | Management interface        |
| 9090      | Prometheus           | Metrics dashboard           |
| 3000      | Grafana             | Visualization dashboard     |
| 16686     | Jaeger UI           | Distributed tracing          |

## Database Initialization

The `scripts/init-databases.sql` file runs automatically on first PostgreSQL startup.
It creates a dedicated database per microservice:

- `invoicing_db`
- `expense_service`
- `customer_service`
- `product_service`
- `settings_db`
- `transport_db`

## Running Tests

### Invoice Service (Node.js/TypeScript)
```bash
cd services/invoicing
npm install
npx vitest run tests/unit/    # 25 unit tests
```

### Customer Service (Node.js/TypeScript)
```bash
cd services/customer
npm install
npx jest tests/unit/          # 34 unit tests
```

### Expense Service (Python/FastAPI)
```bash
cd services/expense
pip install -r requirements.txt
pytest tests/                  # Requires PostgreSQL
```

### Transport Service (Python/FastAPI)
```bash
cd transport-service
pip install -r requirements.txt
pytest tests/                  # Requires PostgreSQL
```

## Stopping the Stack

```bash
# Stop all containers (data preserved)
docker compose -f docker-compose.full.yml down

# Stop and delete all data volumes
docker compose -f docker-compose.full.yml down -v
```

## Troubleshooting

### Port conflicts
If a port is already in use, modify the host port mapping in `docker-compose.full.yml`:
```yaml
ports:
  - "9010:8001"  # Change 8010 to 9010
```

### Kong won't start
Kong requires its migrations to complete first. Check:
```bash
docker compose -f docker-compose.full.yml logs kong-migrations
```

### Service can't connect to database
Ensure PostgreSQL is healthy:
```bash
docker compose -f docker-compose.full.yml ps postgres
```

## Production Considerations

1. **Change all secrets** in `.env` before production deployment
2. **Enable TLS** for all external-facing services
3. **Configure Kong routes** via `kong-routes.yaml` and `kong-services.yaml`
4. **Set up log aggregation** with the ELK stack or Loki
5. **Configure alerts** in Prometheus with Alertmanager
6. **Use managed databases** (RDS, Cloud SQL) instead of containerized PostgreSQL
7. **Enable horizontal scaling** with Docker Swarm or Kubernetes
