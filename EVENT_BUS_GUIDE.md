# Event-Driven Architecture Guide

## Overview

This document describes the comprehensive event-driven integration system built with RabbitMQ, EventBus wrapper, event definitions, handlers, saga patterns for distributed transactions, idempotent handlers, dead letter queues, retry mechanisms with exponential backoff, and Jaeger distributed tracing.

## Architecture Components

### 1. Message Broker (RabbitMQ)

**Location:** `docker-compose.rabbitmq.yml`

RabbitMQ serves as the central message broker for asynchronous event processing.

**Features:**
- Topic-based exchange (`invoiceshelf.events`)
- Durable queues and persistent messages
- Dead letter exchange for failed messages
- Management UI at `http://localhost:15672`

**Setup:**
```bash
docker-compose -f docker-compose.rabbitmq.yml up -d
```

**Configuration:**
- Host: `localhost` (or `rabbitmq` in Docker)
- Port: `5672` (AMQP)
- Management: `15672`
- Default user: `invoiceshelf`
- Default password: `rabbitmq_password`
- Virtual host: `/invoiceshelf`

### 2. Event Bus Service

**Location:** `app/Services/EventBus/`

The EventBus is the central component for publishing and subscribing to events.

#### Publishing Events

```php
use App\Facades\EventBus;
use App\Services\EventBus\Events\InvoiceCreatedEvent;

$event = new InvoiceCreatedEvent(
    invoiceNumber: 'INV-001',
    amount: 1000.00,
    currencyCode: 'USD',
    customerId: 'cust-123',
    aggregateId: 'invoice-456',
    userId: auth()->id(),
    companyId: request()->header('company')
);

$eventId = EventBus::publish($event, [
    'source' => 'api',
    'version' => 'v1',
]);
```

#### Subscribing to Events

```php
use App\Facades\EventBus;
use App\Services\EventBus\Handlers\InvoiceCreatedHandler;

// Subscribe to specific event pattern
EventBus::subscribe('invoice.created', function ($event) {
    // Handle the event
});

// Subscribe with wildcard patterns
EventBus::subscribe('invoice.*', function ($event) {
    // Handle all invoice events
});

// Subscribe to all events
EventBus::subscribe('#', function ($event) {
    // Handle every event
});
```

### 3. Event Definitions

**Location:** `app/Services/EventBus/Events/`

Events represent domain events that occurred in the system.

**Base Event Class:** `app/Services/EventBus/Events/Event.php`

All events extend the base `Event` class which provides:
- Unique event ID (UUID)
- Timestamp
- Correlation ID for tracing
- Causation ID for linking cause and effect
- User and company context
- Aggregate information
- Idempotency key for deduplication

**Available Events:**

1. **InvoiceCreatedEvent**
   - Triggered when an invoice is created
   - Properties: invoiceNumber, amount, currencyCode, customerId

2. **ExpenseSubmittedEvent**
   - Triggered when an expense is submitted for approval
   - Properties: expenseNumber, amount, categoryId, vendorId, expenseDate

3. **CustomerRegisteredEvent**
   - Triggered when a new customer is registered
   - Properties: customerName, email, phone, address, city, country

4. **PaymentReceivedEvent**
   - Triggered when a payment is received
   - Properties: invoiceId, amount, paymentMethod, transactionId

5. **ShipmentCreatedEvent**
   - Triggered when a shipment is created
   - Properties: trackingNumber, origin, destination, weight

### 4. Event Handlers

**Location:** `app/Services/EventBus/Handlers/`

Event handlers process events asynchronously.

**Base Handler Class:** `app/Services/EventBus/Handlers/EventHandler.php`

**Creating a Custom Handler:**

```php
namespace App\Services\EventBus\Handlers;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Events\InvoiceCreatedEvent;

class CustomInvoiceHandler extends EventHandler
{
    public function process(Event $event): void
    {
        if (!$event instanceof InvoiceCreatedEvent) {
            return;
        }

        // Your business logic here
        // This is called after successful processing
    }

    public static function subscribe(): array
    {
        return [
            'invoice.created',      // Specific event
            'invoice.*',            // Pattern matching
            '#'                     // All events
        ];
    }
}
```

**Built-in Handlers:**

1. **InvoiceCreatedHandler**
   - Updates customer statistics
   - Generates invoice PDF
   - Sends notifications
   - Updates accounting records

2. **ExpenseApprovalHandler**
   - Creates approval tasks
   - Routes to appropriate approver
   - Notifies approvers
   - Updates expense status

3. **PaymentReceivedHandler**
   - Marks invoice as paid
   - Updates customer balance
   - Creates accounting entries
   - Generates receipt
   - Sends confirmation

### 5. Saga Pattern for Distributed Transactions

**Location:** `app/Services/EventBus/Saga/`

Sagas orchestrate long-running distributed transactions across multiple domains.

**Base Saga Class:** `app/Services/EventBus/Saga/Saga.php`

**Example: Order Fulfillment Saga**

```php
namespace App\Services\EventBus\Saga;

class OrderFulfillmentSaga extends Saga
{
    public static function define(): array
    {
        return [
            'name' => 'OrderFulfillment',
            'timeout' => 3600,
            'steps' => [
                [
                    'name' => 'CreateInvoice',
                    'triggerOn' => 'InvoiceCreatedEvent',
                    'action' => function (Event $event, Saga $saga) {
                        // Execute invoice creation
                    },
                    'compensate' => function (array $context) {
                        // Rollback: cancel invoice
                    },
                ],
                [
                    'name' => 'ReceivePayment',
                    'triggerOn' => 'PaymentReceivedEvent',
                    'action' => function (Event $event, Saga $saga) {
                        // Execute payment processing
                    },
                    'compensate' => function (array $context) {
                        // Rollback: refund payment
                    },
                ],
                [
                    'name' => 'CreateShipment',
                    'triggerOn' => 'ShipmentCreatedEvent',
                    'action' => function (Event $event, Saga $saga) {
                        // Execute shipment creation
                    },
                    'endsSaga' => true,
                ],
            ],
        ];
    }
}
```

**Saga Features:**

- **Long-running transactions:** Handle workflows spanning multiple domains
- **Compensation logic:** Automatic rollback on failure
- **Timeout handling:** Automatic compensation after timeout
- **Idempotency:** Handle duplicate events gracefully
- **State management:** Persistent saga state in database
- **Event correlation:** Track related events using correlation IDs

### 6. Retry Mechanism with Exponential Backoff

**Location:** `app/Services/EventBus/Retry/RetryPolicy.php`

Automatically retries failed event processing with exponential backoff and optional jitter.

**Configuration** (`config/event-bus.php`):

```php
'retry' => [
    'enabled' => true,
    'max_attempts' => 5,
    'initial_delay' => 1000,        // milliseconds
    'max_delay' => 300000,          // 5 minutes
    'multiplier' => 2,              // exponential backoff multiplier
    'jitter' => true,               // add randomness to prevent thundering herd
],
```

**Usage:**

```php
$retryPolicy = app(RetryPolicy::class);

$result = $retryPolicy->execute(
    callback: function ($attempt) {
        // Your operation
        return process_event($event);
    },
    context: 'ProcessInvoice'
);
```

**Delay Calculation:**

```
Attempt 1: 1000ms
Attempt 2: 2000ms
Attempt 3: 4000ms
Attempt 4: 8000ms
Attempt 5: 16000ms (capped at max_delay: 300000ms)
```

With jitter enabled, delays include ±10% randomness.

### 7. Dead Letter Queue (DLQ)

**Location:** `app/Services/EventBus/DeadLetterQueue/DLQHandler.php`

Failed messages that exceed retry limits are routed to the dead letter queue for manual inspection and recovery.

**DLQ Flow:**

1. Event processing fails
2. Retry attempts exhausted
3. Message moves to DLQ
4. DLQ handler processes the message
5. Message stored in database for inspection
6. Administrators notified
7. Can be manually requeued

**Configuration** (`config/event-bus.php`):

```php
'dead_letter' => [
    'enabled' => true,
    'exchange' => [
        'name' => 'invoiceshelf.dlx',
        'type' => 'topic',
        'durable' => true,
    ],
    'queue' => [
        'name' => 'invoiceshelf.dlq',
        'durable' => true,
        'max_retries' => 5,
        'retention_days' => 30,
    ],
],
```

**DLQ Operations:**

```php
use App\Services\EventBus\DeadLetterQueue\DLQHandler;

$dlq = app(DLQHandler::class);

// Get dead letters with filters
$deadLetters = $dlq->getDeadLetters([
    'event_type' => 'InvoiceCreatedEvent',
    'aggregate_type' => 'Invoice',
    'from_date' => now()->subDays(7),
]);

// Requeue a specific dead letter
$dlq->requeue('message-id-123');

// Cleanup old dead letters
$dlq->cleanup(30); // Delete older than 30 days
```

**Monitoring:**

- RabbitMQ Management UI: `http://localhost:15672` → Queues tab
- Database: `dead_letter_messages` table

### 8. Idempotency

**Location:** `app/Services/EventBus/Events/Event.php`

Ensures events are processed exactly once, preventing duplicate operations.

**Features:**

- Idempotency key extraction from request headers
- Automatic duplicate detection
- TTL-based cleanup of old keys

**Configuration** (`config/event-bus.php`):

```php
'idempotency' => [
    'enabled' => true,
    'storage' => 'database',
    'ttl' => 86400,  // 24 hours in seconds
],
```

**Usage:**

```php
// Client sends idempotency key in request header
curl -X POST https://api.example.com/invoices \
  -H "X-Idempotency-Key: unique-key-123" \
  -H "X-Correlation-ID: correlation-456"

// Event automatically includes idempotency key
$event = new InvoiceCreatedEvent(...);
$event->idempotencyKey; // = 'unique-key-123'

// On retry, event is recognized as duplicate and skipped
```

### 9. Distributed Tracing with Jaeger

**Location:**
- `app/Services/EventBus/Tracing/TracingService.php`
- `docker-compose.rabbitmq.yml` (Jaeger service)

Jaeger provides distributed tracing across the event processing system.

**Features:**

- Trace event flow across services
- Correlation ID and causation ID tracking
- Performance metrics and latencies
- Exception tracking
- Service dependency visualization

**Configuration** (`config/event-bus.php`):

```php
'tracing' => [
    'enabled' => true,
    'driver' => 'jaeger',
    'jaeger' => [
        'host' => 'localhost',
        'port' => 6831,
        'service_name' => 'invoiceshelf-event-bus',
        'sampler' => [
            'type' => 'const',      // Always sample
            'param' => 1,
        ],
    ],
],
```

**Jaeger UI:** `http://localhost:16686`

**Accessing Traces:**

1. Navigate to Jaeger UI
2. Select service: `invoiceshelf-event-bus`
3. View traces by:
   - Correlation ID
   - Event type
   - Service
   - Duration

**Tracing in Custom Code:**

```php
use App\Services\EventBus\Tracing\TracingService;

$tracer = app(TracingService::class);

$span = $tracer->startSpan('custom.operation', [
    'user_id' => auth()->id(),
    'company_id' => request()->header('company'),
]);

try {
    // Your operation
    $tracer->addTag('status', 'success');
} catch (Exception $e) {
    $tracer->recordException($e);
} finally {
    $span->end();
}
```

## Running the System

### Start All Services

```bash
# Start RabbitMQ, Jaeger, and other services
docker-compose -f docker-compose.rabbitmq.yml up -d

# Verify services
curl -i http://localhost:15672   # RabbitMQ Management
curl -i http://localhost:16686   # Jaeger UI
```

### Run Database Migrations

```bash
php artisan migrate
```

### Start Event Bus Listener

```bash
php artisan event-bus:listen
```

### Start DLQ Handler (optional)

```bash
php artisan event-bus:dlq
```

### Cleanup Old Events (scheduled)

```bash
php artisan event-bus:cleanup --days=30
```

## Database Schema

### Tables Created

1. **event_logs** - All published events
2. **idempotent_events** - Deduplication keys
3. **sagas** - Long-running transaction state
4. **saga_events** - Saga event audit trail
5. **dead_letter_messages** - Failed messages
6. **event_handler_status** - Handler processing status
7. **event_metrics** - Performance metrics

## Environment Variables

Add to `.env`:

```bash
# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=invoiceshelf
RABBITMQ_PASSWORD=rabbitmq_password
RABBITMQ_VHOST=/invoiceshelf

# Event Bus
EVENT_BUS_BROKER=rabbitmq
EVENT_BUS_EXCHANGE=invoiceshelf.events

# Retry
EVENT_BUS_RETRY_ENABLED=true
EVENT_BUS_RETRY_MAX_ATTEMPTS=5
EVENT_BUS_RETRY_INITIAL_DELAY=1000
EVENT_BUS_RETRY_MAX_DELAY=300000
EVENT_BUS_RETRY_MULTIPLIER=2
EVENT_BUS_RETRY_JITTER=true

# Dead Letter Queue
EVENT_BUS_DLQ_ENABLED=true
EVENT_BUS_DLQ_QUEUE=invoiceshelf.dlq

# Saga
EVENT_BUS_SAGA_ENABLED=true
EVENT_BUS_SAGA_TIMEOUT=3600

# Tracing with Jaeger
EVENT_BUS_TRACING_ENABLED=true
EVENT_BUS_TRACING_DRIVER=jaeger
JAEGER_HOST=localhost
JAEGER_PORT=6831
JAEGER_SERVICE_NAME=invoiceshelf-event-bus

# Idempotency
EVENT_BUS_IDEMPOTENCY_ENABLED=true
EVENT_BUS_IDEMPOTENCY_STORAGE=database
EVENT_BUS_IDEMPOTENCY_TTL=86400

# Logging
EVENT_BUS_LOGGING_ENABLED=true
EVENT_BUS_LOG_CHANNEL=single
EVENT_BUS_LOG_LEVEL=info
EVENT_BUS_LOG_PAYLOAD=false
```

## Example Workflows

### 1. Publishing an Invoice Created Event

```php
// In InvoiceController
public function store(StoreInvoiceRequest $request)
{
    $invoice = Invoice::create($request->validated());

    // Publish event
    EventBus::publish(
        new InvoiceCreatedEvent(
            invoiceNumber: $invoice->number,
            amount: $invoice->total,
            currencyCode: $invoice->currency_code,
            customerId: $invoice->customer_id,
            aggregateId: $invoice->id,
        ),
        metadata: [
            'source' => 'api',
            'ip_address' => $request->ip(),
        ]
    );

    return response()->json($invoice);
}
```

### 2. Handling the Event

```php
// InvoiceCreatedHandler.php
class InvoiceCreatedHandler extends EventHandler
{
    public function process(Event $event): void
    {
        // Update customer stats
        // Generate PDF
        // Send notification
    }

    public static function subscribe(): array
    {
        return ['invoice.created', 'invoice.*'];
    }
}
```

### 3. Using Sagas for Complex Workflows

```php
// OrderFulfillmentSaga coordinates:
// 1. Invoice creation
// 2. Payment reception
// 3. Shipment creation

// If any step fails, compensations are executed in reverse order
```

## Monitoring and Debugging

### RabbitMQ Management UI

- URL: `http://localhost:15672`
- Username: `invoiceshelf`
- Password: `rabbitmq_password`

**Key Metrics:**
- Queue length
- Message rate
- Consumer count
- Dead letter messages

### Jaeger Distributed Tracing

- URL: `http://localhost:16686`

**Key Features:**
- Search traces by correlation ID
- View service dependencies
- Analyze latencies
- Debug distributed transactions

### Database Queries

```php
// Recent events
DB::table('event_logs')->latest()->limit(10)->get();

// Failed events
DB::table('dead_letter_messages')->latest()->get();

// Saga status
DB::table('sagas')->where('state', 'running')->get();

// Event processing metrics
DB::table('event_metrics')->latest('date')->get();
```

## Troubleshooting

### Events Not Being Processed

1. Check if listener is running: `php artisan event-bus:listen`
2. Verify RabbitMQ connection: `curl -i http://localhost:15672`
3. Check event_logs table for published events
4. Verify handlers are subscribed: `EventBus::subscribe()`

### Messages in Dead Letter Queue

1. Check error in dead_letter_messages table
2. Review logs for handler exceptions
3. Fix the underlying issue
4. Requeue: `DLQHandler::requeue('message-id')`

### High Latency

1. Check Jaeger traces for bottlenecks
2. Monitor RabbitMQ queue depth
3. Increase listener concurrency
4. Optimize handler logic

### Duplicate Processing

1. Check idempotency key configuration
2. Verify database has idempotent_events table
3. Ensure EVENT_BUS_IDEMPOTENCY_ENABLED=true

## Best Practices

1. **Always include context:** Use correlation IDs and causation IDs
2. **Handle idempotency:** Use idempotency keys for critical operations
3. **Set reasonable timeouts:** Configure saga timeouts appropriately
4. **Monitor dead letters:** Set up alerts for DLQ messages
5. **Use correlation IDs:** For tracing related events
6. **Log appropriately:** Log important business events
7. **Test sagas:** Test compensation logic thoroughly
8. **Handle failures gracefully:** Implement proper error handling
9. **Clean up regularly:** Run cleanup commands to manage data growth
10. **Monitor performance:** Use Jaeger to identify bottlenecks

## API Reference

### EventBus Facade

```php
// Publish an event
EventBus::publish(Event $event, array $metadata = []): string

// Subscribe to event pattern
EventBus::subscribe(string $pattern, callable $handler, ?string $queue = null): void

// Unsubscribe
EventBus::unsubscribe(string $pattern, ?string $queue = null): void

// Start listening
EventBus::listen(?string $queue = null, int $timeout = 0): void

// Get metadata
EventBus::getMetadata(string $eventId): array

// Replay events
EventBus::replay(DateTime $from, ?DateTime $to = null, ?string $pattern = null): void

// Cleanup
EventBus::cleanup(int $daysOld = 30): int
```

## Support

For issues or questions:
1. Check RabbitMQ logs: `docker logs invoiceshelf-rabbitmq`
2. Check Jaeger UI for traces
3. Review event_logs table
4. Check application logs in `storage/logs/`

---

**Last Updated:** 2024-01-01
**Version:** 1.0.0
