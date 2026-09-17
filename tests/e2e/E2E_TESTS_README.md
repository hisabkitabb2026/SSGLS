# End-to-End Testing Guide

Comprehensive end-to-end tests for the InvoiceShelf microservices architecture. Tests complete user workflows across all 6 services with Docker orchestration.

## Overview

### Test Coverage

The E2E test suite covers:

1. **User Journeys** (`test_e2e_user_journeys.py`)
   - Complete business workflows
   - Multi-service interactions
   - Data flow across services

2. **Event Propagation** (`test_e2e_event_propagation.py`)
   - Cross-service events
   - Event ordering and sequencing
   - Event retry and error handling
   - Dead letter queue processing

3. **API Gateway & Auth** (`test_e2e_api_gateway_auth.py`)
   - Kong API gateway routing
   - JWT token validation
   - Authorization and permissions
   - Rate limiting
   - CORS handling

4. **Data Consistency** (`test_e2e_data_consistency.py`)
   - Cross-service data synchronization
   - Reference integrity
   - Eventual consistency
   - Conflict resolution
   - Duplication prevention

## Architecture

### Services

- **Invoice Service** (8001) - Invoice and payment management
- **Expense Service** (8002) - Expense tracking
- **Product Service** (8003) - Product catalog
- **Customer Service** (8004) - Customer management
- **Settings Service** (8005) - Company configuration
- **Transport Service** (8006) - Logistics and shipping

### Infrastructure

- **MySQL** - Shared database (with per-service isolation)
- **Redis** - Caching layer
- **RabbitMQ** - Event bus
- **Kong API Gateway** - API routing and auth
- **Jaeger** - Distributed tracing

## Prerequisites

### System Requirements

- Docker 20.10+
- Docker Compose 2.0+
- Python 3.9+
- 8+ GB RAM
- 20+ GB free disk space

### Installation

1. **Install Python dependencies:**

```bash
pip install -r requirements_e2e.txt
```

2. **Install Docker (if not already installed):**

```bash
# macOS with Homebrew
brew install docker docker-compose

# Ubuntu/Debian
sudo apt-get install docker.io docker-compose

# Or use Docker Desktop
```

3. **Verify installations:**

```bash
docker --version
docker-compose --version
python3 --version
pytest --version
```

## Quick Start

### Run All Tests

```bash
# Make script executable
chmod +x run_e2e_tests.sh

# Run all tests (starts services, runs tests, cleans up)
./run_e2e_tests.sh

# Run with HTML report
./run_e2e_tests.sh --report

# Run in parallel (faster)
./run_e2e_tests.sh --parallel
```

### Run Specific Test Categories

```bash
# User journey tests
./run_e2e_tests.sh --journey

# Authentication tests
./run_e2e_tests.sh --auth

# API gateway routing tests
./run_e2e_tests.sh --gateway

# Event propagation tests
./run_e2e_tests.sh --events

# Data consistency tests
./run_e2e_tests.sh --consistency
```

### Manual Service Management

```bash
# Start services
docker-compose -f docker-compose.tests.yml up -d

# Check service status
docker-compose -f docker-compose.tests.yml ps

# View logs
docker-compose -f docker-compose.tests.yml logs -f [service-name]

# Stop services
docker-compose -f docker-compose.tests.yml down

# Clean up volumes
docker-compose -f docker-compose.tests.yml down -v
```

## Running Tests

### Using Pytest Directly

```bash
# All tests
pytest -c pytest_e2e.ini -v

# Specific test file
pytest -c pytest_e2e.ini test_e2e_user_journeys.py -v

# Specific test class
pytest -c pytest_e2e.ini test_e2e_user_journeys.py::TestInvoiceCreationJourney -v

# Specific test
pytest -c pytest_e2e.ini test_e2e_user_journeys.py::TestInvoiceCreationJourney::test_create_and_publish_invoice -v

# With markers
pytest -c pytest_e2e.ini -m "journey" -v
pytest -c pytest_e2e.ini -m "critical" -v

# With keyword filter
pytest -c pytest_e2e.ini -k "customer" -v

# Parallel execution
pytest -c pytest_e2e.ini -n auto -v

# With coverage
pytest -c pytest_e2e.ini --cov --cov-report=html -v

# Show slowest tests
pytest -c pytest_e2e.ini -v --durations=10
```

## Test Structure

### Fixtures (conftest.py)

#### Service Clients
- `invoice_client` - Authenticated invoice service client
- `expense_client` - Authenticated expense service client
- `product_client` - Authenticated product service client
- `customer_client` - Authenticated customer service client
- `settings_client` - Authenticated settings service client
- `transport_client` - Authenticated transport service client
- `kong_client` - Kong API gateway client

#### Authentication
- `valid_token` - Valid JWT token
- `expired_token` - Expired JWT token
- `invalid_token` - Invalid JWT token
- `auth_headers` - Authorization headers with valid token

#### Sample Data
- `sample_company_data` - Company creation data
- `sample_customer_data` - Customer creation data
- `sample_product_data` - Product creation data
- `sample_invoice_data` - Invoice creation data
- `sample_expense_data` - Expense creation data
- `sample_transport_data` - Transport creation data

#### Utilities
- `event_capture` - Capture and verify event propagation
- `data_consistency_checker` - Verify cross-service data consistency
- `mock_event_bus` - Mock event bus for testing

### Test Classes

#### User Journeys
- `TestCompanyCreationJourney` - Company setup
- `TestCustomerCreationJourney` - Customer management
- `TestProductCatalogJourney` - Product management
- `TestInvoiceCreationJourney` - Invoice lifecycle
- `TestExpenseJourney` - Expense processing
- `TestTransportJourney` - Transport tracking
- `TestFullEndToEndWorkflow` - Complete business cycle

#### Event Propagation
- `TestEventPropagation` - Event publishing and consumption
- `TestEventOrdering` - Event sequence validation
- `TestEventErrorHandling` - Retry and error handling
- `TestCrossServiceEventConsumption` - Multi-service event flow
- `TestEventIdempotency` - Duplicate event handling

#### API Gateway & Auth
- `TestKongAPIGatewayRouting` - Request routing
- `TestJWTAuthentication` - Token validation
- `TestAuthorizationAndPermissions` - Permission checks
- `TestRateLimiting` - Rate limit enforcement
- `TestCORSHandling` - CORS headers
- `TestRequestResponseTransformation` - Data transformation
- `TestGatewayErrorHandling` - Error responses

#### Data Consistency
- `TestCrossServiceDataSynchronization` - Data sync verification
- `TestReferenceIntegrity` - Foreign key validation
- `TestEventualConsistency` - Consistency guarantee validation
- `TestConflictResolution` - Concurrent update handling
- `TestDataDuplicationPrevention` - Uniqueness constraints
- `TestDataIntegrityAcrossServices` - Overall integrity

## Frontend Tests (Jest)

### Jest Fixtures (jest.fixtures.ts)

Provides TypeScript fixtures for frontend testing:

```typescript
import {
  mockAuthToken,
  mockCustomerData,
  mockInvoiceData,
  MockApiClient,
  createMockCustomer,
  mockFetch,
  mockStoreState,
} from './jest.fixtures'

// Use in tests
describe('CustomerForm', () => {
  it('creates a customer', async () => {
    const client = new MockApiClient()
    client.setToken(mockAuthToken.valid)

    const response = await client.post('/customers', mockCustomerData.valid)
    expect(response.status).toBe(201)
  })
})
```

### Available Fixtures

- Auth: `mockAuthToken`, `mockAuthUser`, `mockAuthHeaders()`
- Company: `mockCompanyData`, `mockCompanyResponse`
- Customers: `mockCustomerData`, `mockCustomerResponse`, `mockCustomersListResponse`
- Products: `mockProductData`, `mockProductResponse`
- Invoices: `mockInvoiceData`, `mockInvoiceResponse`, `mockInvoiceListResponse`
- Payments: `mockPaymentData`, `mockPaymentResponse`
- Expenses: `mockExpenseData`, `mockExpenseResponse`
- Transport: `mockTransportData`, `mockTransportResponse`
- Events: `mockEventData`
- Utilities: `createMockCustomer()`, `createMockInvoice()`, etc.

## Configuration

### Environment Variables

Create `.env.test` file:

```env
# Services
INVOICE_SERVICE_URL=http://localhost:8001
EXPENSE_SERVICE_URL=http://localhost:8002
PRODUCT_SERVICE_URL=http://localhost:8003
CUSTOMER_SERVICE_URL=http://localhost:8004
SETTINGS_SERVICE_URL=http://localhost:8005
TRANSPORT_SERVICE_URL=http://localhost:8006
KONG_URL=http://localhost:8000

# Auth
JWT_SECRET_KEY=test-secret-key
JWT_ALGORITHM=HS256
JWT_EXPIRY_HOURS=24

# Database
DATABASE_URL=mysql://test_user:test_password@localhost:3306/invoiceshelf_test
REDIS_URL=redis://localhost:6379/0
RABBITMQ_URL=amqp://test_user:test_password@localhost:5672/

# Logging
LOG_LEVEL=DEBUG
```

### Pytest Configuration (pytest_e2e.ini)

Customize test behavior:

```ini
[pytest]
timeout = 300              # Test timeout
addopts = -v --tb=short   # Output options
testpaths = .              # Test directory
markers =                  # Test markers
    journey: User journey tests
    auth: Auth tests
    ...
```

## Reporting

### Test Reports

After running tests, reports are available at:

```
reports/
├── report.html              # HTML test report
├── results.xml             # JUnit XML results
└── coverage/               # Code coverage report
    └── index.html
```

### View Reports

```bash
# Open HTML report in browser
open reports/report.html

# View coverage
open reports/coverage/index.html

# View logs
tail -f e2e_tests.log
```

## Troubleshooting

### Services Won't Start

```bash
# Check Docker
docker ps

# View compose logs
docker-compose -f docker-compose.tests.yml logs

# Recreate services
docker-compose -f docker-compose.tests.yml down -v
docker-compose -f docker-compose.tests.yml up -d
```

### Tests Timeout

```bash
# Increase timeout in pytest_e2e.ini
timeout = 600

# Or via command line
pytest -c pytest_e2e.ini --timeout=600 -v
```

### Connection Refused

```bash
# Wait for services to be healthy
docker-compose -f docker-compose.tests.yml ps

# Check if services are running
curl http://localhost:8001/health
curl http://localhost:8002/health
# ... etc for other services
```

### JWT Token Issues

```bash
# Verify token in conftest.py
python3 -c "import jwt; print(jwt.decode('token', 'secret', algorithms=['HS256']))"
```

### Database Issues

```bash
# Check MySQL
mysql -h localhost -u test_user -p invoiceshelf_test

# Reset database
docker-compose -f docker-compose.tests.yml exec mysql mysql -u root -p -e "DROP DATABASE invoiceshelf_test; CREATE DATABASE invoiceshelf_test;"
```

## Performance Optimization

### Parallel Execution

```bash
# Run tests in parallel (requires pytest-xdist)
./run_e2e_tests.sh --parallel

# Or with pytest directly
pytest -c pytest_e2e.ini -n auto -v
```

### Selective Testing

```bash
# Run only critical tests
pytest -c pytest_e2e.ini -m "critical" -v

# Skip slow tests
pytest -c pytest_e2e.ini -m "not slow" -v

# Run specific journey
pytest -c pytest_e2e.ini -k "invoice" -v
```

## Debugging

### Enable Debug Logging

```bash
# In pytest
pytest -c pytest_e2e.ini --log-cli-level=DEBUG -v

# Or in conftest.py
os.environ['LOG_LEVEL'] = 'DEBUG'
```

### Inspect Service Logs

```bash
# All services
docker-compose -f docker-compose.tests.yml logs -f

# Specific service
docker-compose -f docker-compose.tests.yml logs -f invoice-service

# With grep filter
docker-compose -f docker-compose.tests.yml logs invoice-service | grep ERROR
```

### Test in Isolation

```bash
# Run single test with maximum verbosity
pytest -c pytest_e2e.ini test_e2e_user_journeys.py::TestInvoiceCreationJourney::test_create_and_publish_invoice -vv -s

# Keep services running for debugging
# (comment out cleanup in run_e2e_tests.sh)
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e-tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Set up Python
        uses: actions/setup-python@v4
        with:
          python-version: '3.10'

      - name: Install dependencies
        run: pip install -r requirements_e2e.txt

      - name: Run E2E tests
        run: ./run_e2e_tests.sh --parallel --report

      - name: Upload reports
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: e2e-reports
          path: reports/
```

## Contributing

### Adding New Tests

1. Create test in appropriate file:
   - `test_e2e_user_journeys.py` - User workflows
   - `test_e2e_event_propagation.py` - Event tests
   - `test_e2e_api_gateway_auth.py` - Gateway/Auth tests
   - `test_e2e_data_consistency.py` - Consistency tests

2. Use appropriate fixtures:
   ```python
   def test_something(invoice_client, auth_headers, sample_invoice_data):
       response = invoice_client.post(
           "/api/v1/invoices",
           json=sample_invoice_data,
           headers=auth_headers
       )
       assert response.status_code in [200, 201]
   ```

3. Add test markers:
   ```python
   @pytest.mark.journey
   def test_complete_workflow(...):
       ...
   ```

4. Run tests locally before committing:
   ```bash
   ./run_e2e_tests.sh
   ```

## References

- [Pytest Documentation](https://docs.pytest.org/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Kong API Gateway](https://konghq.com/)
- [RabbitMQ](https://www.rabbitmq.com/)
- [Jaeger Tracing](https://www.jaegertracing.io/)

## Support

For issues and questions:
- Review test logs: `e2e_tests.log`
- Check service logs: `docker-compose logs`
- Review test output: `reports/report.html`
- Check test code comments for specific test purposes
