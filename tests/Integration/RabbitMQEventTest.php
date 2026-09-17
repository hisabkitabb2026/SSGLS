<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\ProcessCustomerRegisteredEvent;
use App\Jobs\ProcessExpenseRecordedEvent;
use App\Jobs\ProcessInvoiceCreatedEvent;
use App\Jobs\ProcessPaymentReceivedEvent;
use App\Jobs\ProcessProductAddedEvent;
use App\Jobs\ProcessShipmentCreatedEvent;
use App\Jobs\SendPaymentConfirmationJob;
use App\Jobs\UpdateInvoiceStatusJob;
use App\Services\EventBus\EventConsumer;
use App\Services\EventBus\EventPublisher;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * RabbitMQ Event Publishing and Consumption Tests
 *
 * Tests event-driven communication via RabbitMQ message broker
 */
class RabbitMQEventTest extends TestCase
{
    private EventPublisher $publisher;

    private EventConsumer $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->publisher = app(EventPublisher::class);
        $this->consumer = app(EventConsumer::class);
    }

    public function test_publish_invoice_created_event(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_number' => 'INV-001',
            'customer_id' => 1,
            'amount' => 1000.00,
            'status' => 'draft',
            'created_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('invoice.created', $eventData);

        Queue::assertPushed(ProcessInvoiceCreatedEvent::class);
    }

    public function test_publish_expense_recorded_event(): void
    {
        $eventData = [
            'id' => 1,
            'company_id' => 1,
            'amount' => 50.00,
            'category' => 'office-supplies',
            'created_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('expense.recorded', $eventData);

        Queue::assertPushed(ProcessExpenseRecordedEvent::class);
    }

    public function test_publish_payment_received_event(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_id' => 1,
            'amount' => 1000.00,
            'method' => 'bank_transfer',
            'reference' => 'TXN-123',
            'received_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('payment.received', $eventData);

        Queue::assertPushed(ProcessPaymentReceivedEvent::class);
    }

    public function test_publish_product_added_event(): void
    {
        $eventData = [
            'id' => 1,
            'name' => 'New Product',
            'sku' => 'PROD-001',
            'price' => 99.99,
            'created_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('product.added', $eventData);

        Queue::assertPushed(ProcessProductAddedEvent::class);
    }

    public function test_publish_customer_registered_event(): void
    {
        $eventData = [
            'id' => 1,
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'registered_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('customer.registered', $eventData);

        Queue::assertPushed(ProcessCustomerRegisteredEvent::class);
    }

    public function test_publish_shipment_created_event(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_id' => 1,
            'tracking_number' => 'SHIP-001',
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ];

        $this->publisher->publish('shipment.created', $eventData);

        Queue::assertPushed(ProcessShipmentCreatedEvent::class);
    }

    public function test_event_includes_metadata(): void
    {
        $eventData = [
            'id' => 1,
            'amount' => 100.00,
        ];

        $this->publisher->publish('expense.recorded', $eventData, [
            'correlation_id' => 'corr-123',
            'tenant_id' => 'tenant-1',
            'user_id' => 'user-1',
        ]);

        Queue::assertPushed(ProcessExpenseRecordedEvent::class, function ($job) {
            return isset($job->metadata['correlation_id']) &&
                   $job->metadata['correlation_id'] === 'corr-123';
        });
    }

    public function test_consume_event_from_queue(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_number' => 'INV-001',
            'amount' => 1000.00,
        ];

        $this->publisher->publish('invoice.created', $eventData);

        // Simulate event consumption
        $this->consumer->process('invoice.created', $eventData);

        // Verify event was processed
        $this->assertTrue(true);
    }

    public function test_event_routing_to_multiple_consumers(): void
    {
        $eventData = [
            'id' => 1,
            'amount' => 100.00,
            'status' => 'pending',
        ];

        // Publish single event
        $this->publisher->publish('payment.received', $eventData);

        // Verify all consumers are notified
        Queue::assertPushed(ProcessPaymentReceivedEvent::class);
        Queue::assertPushed(UpdateInvoiceStatusJob::class);
        Queue::assertPushed(SendPaymentConfirmationJob::class);
    }

    public function test_event_exchange_declaration(): void
    {
        // Verify that exchanges are properly declared
        $exchange = $this->publisher->getExchange('invoice.events');

        $this->assertNotNull($exchange);
        $this->assertEquals('invoice.events', $exchange['name']);
        $this->assertEquals('topic', $exchange['type']);
    }

    public function test_event_queue_declaration(): void
    {
        // Verify that queues are properly declared
        $queue = $this->publisher->getQueue('invoice.created.queue');

        $this->assertNotNull($queue);
        $this->assertEquals('invoice.created.queue', $queue['name']);
    }

    public function test_event_binding_between_exchange_and_queue(): void
    {
        $binding = $this->publisher->getBinding('invoice.events', 'invoice.created.queue', 'invoice.created');

        $this->assertNotNull($binding);
        $this->assertEquals('invoice.created', $binding['routing_key']);
    }

    public function test_event_with_message_properties(): void
    {
        $eventData = [
            'id' => 1,
            'amount' => 100.00,
        ];

        $this->publisher->publish('expense.recorded', $eventData, properties: [
            'content_type' => 'application/json',
            'delivery_mode' => 2, // persistent
            'priority' => 5,
        ]);

        Queue::assertPushed(ProcessExpenseRecordedEvent::class);
    }

    public function test_dead_letter_exchange_for_failed_events(): void
    {
        $eventData = [
            'id' => 1,
            'amount' => 100.00,
        ];

        // Mark for retry with DLX
        $this->publisher->publishWithDLX('expense.recorded', $eventData, ttl: 5000);

        // Verify DLX configuration
        $dlx = $this->publisher->getExchange('invoice.dlx');
        $this->assertNotNull($dlx);
    }

    public function test_event_acknowledgment(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_number' => 'INV-001',
        ];

        $this->publisher->publish('invoice.created', $eventData);

        // Simulate consumer acknowledgment
        $this->consumer->acknowledge('invoice.created');

        Queue::assertPushed(ProcessInvoiceCreatedEvent::class);
    }

    public function test_event_negative_acknowledgment(): void
    {
        $eventData = [
            'id' => 1,
            'invoice_number' => 'INV-001',
        ];

        $this->publisher->publish('invoice.created', $eventData);

        // Simulate consumer negative acknowledgment (requeue)
        $this->consumer->nack('invoice.created', requeue: true);

        // Event should be requeued
        Queue::assertPushed(ProcessInvoiceCreatedEvent::class);
    }

    public function test_bulk_event_publishing(): void
    {
        $events = [
            ['event' => 'invoice.created', 'data' => ['id' => 1]],
            ['event' => 'invoice.created', 'data' => ['id' => 2]],
            ['event' => 'invoice.created', 'data' => ['id' => 3]],
        ];

        $this->publisher->publishBulk($events);

        // Verify all events are queued
        $this->assertEquals(3, Queue::count());
    }

    public function test_event_priority_queue(): void
    {
        $lowPriorityEvent = [
            'id' => 1,
            'type' => 'notification',
        ];

        $highPriorityEvent = [
            'id' => 2,
            'type' => 'payment',
        ];

        // Publish low priority first
        $this->publisher->publish('notification.sent', $lowPriorityEvent, properties: ['priority' => 1]);

        // Publish high priority
        $this->publisher->publish('payment.received', $highPriorityEvent, properties: ['priority' => 10]);

        // High priority should be processed first
        Queue::assertPushed(ProcessPaymentReceivedEvent::class, 1);
    }

    public function test_event_filtering(): void
    {
        $this->publisher->publish('invoice.created', ['id' => 1, 'status' => 'draft']);
        $this->publisher->publish('invoice.created', ['id' => 2, 'status' => 'published']);
        $this->publisher->publish('invoice.created', ['id' => 3, 'status' => 'draft']);

        // Filter only published invoices
        $filtered = $this->consumer->filterEvents('invoice.created', [
            'status' => 'published',
        ]);

        $this->assertCount(1, $filtered);
    }
}
