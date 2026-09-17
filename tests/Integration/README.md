# Integration Tests for Service-to-Service Communication

This directory contains comprehensive integration tests for microservices architecture in InvoiceShelf, covering service discovery, inter-service REST calls, event-driven messaging, distributed transactions, resilience patterns, and security.

## Test Suites

### 1. Service Discovery (`ServiceDiscoveryTest.php`)

Tests for microservice discovery and registry management:

- **Service Registration Verification**: Ensures all services are properly registered
- **Service Discovery**: Tests service lookup by name and metadata
- **Health Status**: Verifies service health check endpoints
- **Cache Management**: Tests discovery cache invalidation and refresh
- **Registry Integrity**: Validates required registry fields and configurations

**Key Tests:**
- `test_service_discovery_registry_available()` - Verifies service discovery is enabled
- `test_discover_service_by_name()` - Tests service lookup
- `test_service_discovery_returns_healthy_services()` - Filters healthy services
- `test_service_discovery_invalidates_cache_on_failure()` - Cache invalidation

**Use Cases:**
- Initial service startup and discovery
- Service endpoint resolution
- Health status monitoring
- Dynamic service registry updates

---

### 2. Service REST Calls (`ServiceRestCallsTest.php`)

Tests for REST API communication between microservices:

- **HTTP Methods**: GET, POST, PATCH, DELETE operations
- **Request/Response Handling**: JSON serialization, headers, status codes
- **Service Chaining**: Multi-step service interactions
- **Authentication**: Bearer token propagation
- **Error Handling**: Error responses and exception handling

**Key Tests:**
- `test_make_rest_call_to_service()` - Basic REST call
- `test_service_rest_call_with_headers()` - Custom headers and tokens
- `test_service_post_request()` - POST operations
- `test_service_rest_call_batch_requests()` - Batch processing
- `test_rest_call_with_query_parameters()` - Query parameter handling
- `test_service_chained_rest_calls()` - Multi-step workflows

**Services Tested:**
- Invoice Service (Port 8001)
- Expense Service (Port 8002)
- Product Service (Port 8003)
- Customer Service (Port 8004)
- Settings Service (Port 8005)
- Transport Service (Port 8006)

---

### 3. RabbitMQ Event Publishing/Consumption (`RabbitMQEventTest.php`)

Tests for event-driven messaging via RabbitMQ:

- **Event Publishing**: Publishing events to message broker
- **Event Consumption**: Consuming and processing events
- **Message Properties**: Content type, delivery mode, priorities
- **Exchange/Queue Management**: Declaring and binding exchanges to queues
- **Message Routing**: Topic-based routing with binding keys
- **Acknowledgment**: Message ACK/NACK handling

**Key Tests:**
- `test_publish_invoice_created_event()` - Publishing events
- `test_event_includes_metadata()` - Event metadata
- `test_event_routing_to_multiple_consumers()` - Multi-consumer routing
- `test_event_exchange_declaration()` - Exchange setup
- `test_event_binding_between_exchange_and_queue()` - Queue bindings
- `test_bulk_event_publishing()` - Batch event publishing
- `test_event_priority_queue()` - Priority message handling

**Event Types Tested:**
- `invoice.created`
- `expense.recorded`
- `payment.received`
- `product.added`
- `customer.registered`
- `shipment.created`

---

### 4. Saga Pattern - Distributed Transactions (`SagaPatternTest.php`)

Tests for saga pattern implementation:

- **Saga Creation**: Creating distributed transactions
- **Step Execution**: Multi-step saga workflows
- **Compensation**: Rolling back failed steps
- **State Persistence**: Persisting saga state
- **Idempotency**: Preventing duplicate execution
- **Parallel Steps**: Concurrent step execution
- **Event Sourcing**: Event-driven state reconstruction

**Key Tests:**
- `test_create_simple_saga_transaction()` - Basic saga creation
- `test_saga_multi_step_workflow()` - Multi-step sagas
- `test_saga_step_failure_triggers_compensation()` - Failure handling
- `test_saga_compensation_on_failure()` - Compensation execution
- `test_saga_parallel_steps()` - Parallel execution
- `test_saga_idempotency()` - Duplicate prevention
- `test_saga_state_persistence()` - State storage

**Saga Scenarios:**
- Create Invoice → Process Payment → Create Shipment
- Order Processing with compensation on failure
- Event sourcing and replay

---

### 5. Timeout and Retry Handling (`TimeoutRetryTest.php`)

Tests for resilience patterns:

- **Timeout Policies**: Request timeout configuration and handling
- **Retry Policies**: Configurable retry strategies
- **Exponential Backoff**: Progressive delay increases
- **Jitter**: Randomization to prevent thundering herd
- **Custom Conditions**: Conditional retry logic
- **Fallback Mechanisms**: Default responses on failure
- **Statistics**: Tracking retry attempts and success rates

**Key Tests:**
- `test_request_timeout_handling()` - Timeout configuration
- `test_retry_on_transient_failure()` - Basic retry logic
- `test_retry_exponential_backoff()` - Backoff strategy
- `test_retry_max_attempts_exceeded()` - Retry limits
- `test_timeout_combined_with_retry()` - Combined strategies
- `test_retry_statistics_tracking()` - Metrics collection
- `test_timeout_with_fallback()` - Fallback responses

**Configuration Options:**
- Max attempts: 1-10
- Initial delay: 100-1000ms
- Exponential multiplier: 1.5-2.0
- Max delay: 30000ms
- Jitter: true/false
- Fallback behavior

---

### 6. Circuit Breaker Pattern (`CircuitBreakerTest.php`)

Tests for circuit breaker implementation:

- **State Management**: CLOSED, OPEN, HALF_OPEN states
- **Failure Detection**: Triggering circuit opening
- **Recovery**: Half-open state and recovery
- **Fallback**: Default responses when circuit is open
- **Metrics**: Failure/success tracking
- **Configuration**: Per-service thresholds

**Key Tests:**
- `test_circuit_breaker_initial_state_is_closed()` - Initial state
- `test_circuit_breaker_opens_after_failure_threshold()` - Failure trigger
- `test_circuit_breaker_half_open_after_timeout()` - Timeout handling
- `test_circuit_breaker_half_open_closes_on_success()` - Recovery
- `test_circuit_breaker_with_fallback()` - Fallback responses
- `test_circuit_breaker_metrics()` - Metrics tracking
- `test_circuit_breaker_error_rate_threshold()` - Error rate based opening

**States:**
```
CLOSED (normal operation)
  ↓ (failures exceed threshold)
OPEN (rejecting requests)
  ↓ (timeout expires)
HALF_OPEN (testing recovery)
  ↓ (success → back to normal, failure → reopen)
```

---

### 7. Dead Letter Queue Processing (`DeadLetterQueueTest.php`)

Tests for handling failed messages:

- **DLQ Management**: Moving failed messages to DLQ
- **Message Persistence**: Storing failed messages
- **Retry Mechanism**: Requeuing messages
- **Backoff Strategy**: Exponential backoff on retry
- **Message Filtering**: Filtering by event type or age
- **Archiving**: Long-term storage of failed messages
- **Replay**: Reprocessing archived messages

**Key Tests:**
- `test_message_moved_to_dlq_after_max_retries()` - DLQ movement
- `test_dlq_message_persistence()` - Message storage
- `test_dlq_message_retry_from_queue()` - Requeuing
- `test_dlq_handler_with_exponential_backoff()` - Backoff calculation
- `test_dlq_message_filtering()` - Event filtering
- `test_dlq_message_age_filtering()` - Age-based filtering
- `test_dlq_archive_old_messages()` - Message archiving
- `test_dlq_replay_messages()` - Message replay

**Message Lifecycle:**
```
Service Queue
  ↓ (processing fails, retries exhausted)
Dead Letter Queue
  ↓ (optional: after retention period)
Archive
  ↓ (can be replayed for recovery)
```

---

### 8. Service Authorization (`ServiceAuthorizationTest.php`)

Tests for service-to-service authentication and authorization:

- **Service Tokens**: JWT-based service authentication
- **Token Claims**: Scopes, roles, tenant IDs
- **RBAC**: Role-based access control
- **Tenant Isolation**: Multi-tenant data isolation
- **API Keys**: Static API key authentication
- **Mutual TLS**: Certificate-based authentication
- **Rate Limiting**: Per-service rate limits
- **Audit Logging**: Call tracing and logging

**Key Tests:**
- `test_service_token_generation()` - Token creation
- `test_service_token_includes_claims()` - Token claims
- `test_service_token_expiration()` - Token lifetime
- `test_service_authorization_scopes()` - Permission checking
- `test_service_role_based_access_control()` - Role validation
- `test_service_tenant_isolation()` - Tenant security
- `test_api_key_based_service_auth()` - API key auth
- `test_mutual_tls_authentication()` - mTLS
- `test_rate_limiting_per_service()` - Rate limits
- `test_cross_service_permission_delegation()` - Delegation

**Authentication Methods:**
1. **JWT Tokens** - Service identity and claims
2. **API Keys** - Static shared secrets
3. **Mutual TLS** - Certificate-based
4. **OAuth 2.0** - Token-based with scopes

---

## Running Tests

### Run All Integration Tests
```bash
php artisan test tests/Integration
```

### Run Specific Test Suite
```bash
php artisan test tests/Integration/ServiceDiscoveryTest.php
php artisan test tests/Integration/CircuitBreakerTest.php
```

### Run with Specific Filter
```bash
php artisan test --filter "test_circuit_breaker_opens_after"
```

### Run with Coverage
```bash
php artisan test tests/Integration --coverage
```

### Run in Parallel
```bash
php artisan test tests/Integration --parallel
```

---

## Test Configuration

### Services Registry

Configure service endpoints in `config/services.php`:

```php
'registry' => [
    'invoice' => [
        'name' => 'invoice-service',
        'url' => 'http://invoice-service:8001',
        'health_check' => 'http://invoice-service:8001/health',
        'version' => '1.0.0',
        'timeout' => 30,
        'retries' => 3,
    ],
    // ... other services
],
```

### Event Configuration

Configure RabbitMQ in `config/queue.php`:

```php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
],
```

---

## Docker Compose Setup

### Start Microservices Stack
```bash
docker-compose -f docker-compose.microservices.yml \
               -f docker-compose.kong.yml \
               -f docker-compose.rabbitmq.yml \
               up -d
```

### Monitor Services
```bash
docker-compose ps
docker-compose logs -f invoice-service
```

### Access Services
- **Invoice Service**: http://localhost:8001
- **RabbitMQ Management**: http://localhost:15672
- **Kong API Gateway**: http://localhost:8000

---

## Key Patterns Tested

### 1. Service Discovery
- Static registry configuration
- Dynamic service lookup
- Health check integration

### 2. Resilience
- Timeouts and deadlines
- Retry strategies (exponential backoff, jitter)
- Circuit breakers (CLOSED → OPEN → HALF_OPEN)
- Fallback mechanisms

### 3. Event-Driven Architecture
- Topic-based pub/sub (RabbitMQ)
- Event routing with binding keys
- Consumer groups
- Message acknowledgment

### 4. Distributed Transactions
- Saga pattern with compensation
- Two-phase commit simulation
- Event sourcing for state reconstruction

### 5. Security
- Service authentication (JWT, API Keys, mTLS)
- Authorization (RBAC, scopes)
- Tenant isolation
- Audit logging

### 6. Failure Handling
- Dead letter queues
- Message replay
- Exponential backoff
- Circuit breaker recovery

---

## Best Practices

1. **Use Correlation IDs**: Track requests across services
2. **Implement Timeouts**: Always set reasonable timeouts
3. **Add Retries Carefully**: Only retry idempotent operations
4. **Use Circuit Breakers**: Prevent cascading failures
5. **Handle Partial Failures**: Use sagas for distributed transactions
6. **Log Everything**: Comprehensive audit trails
7. **Monitor Metrics**: Track success rates, latencies, error rates
8. **Test Failure Scenarios**: Don't just test happy paths

---

## Troubleshooting

### Service Discovery Issues
- Check service registry configuration
- Verify service health endpoints
- Check network connectivity between services

### Message Queue Issues
- Verify RabbitMQ connection
- Check exchange/queue declarations
- Review binding configurations

### Authorization Failures
- Verify token generation and claims
- Check scopes and permissions
- Review tenant isolation logic

### Circuit Breaker Issues
- Check failure threshold configuration
- Verify timeout settings
- Review metrics and logs

---

## References

- [RabbitMQ Documentation](https://www.rabbitmq.com/documentation.html)
- [Saga Pattern](https://microservices.io/patterns/data/saga.html)
- [Circuit Breaker Pattern](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Service Discovery](https://microservices.io/patterns/service-discovery.html)

---

## Contributing

When adding new integration tests:

1. Follow existing test structure
2. Use descriptive test names
3. Add comprehensive documentation
4. Include both success and failure scenarios
5. Update this README with new test information

---

## Maintenance

- Review and update service registry regularly
- Monitor test execution times
- Keep dependencies updated
- Review failed tests and adjust thresholds as needed
