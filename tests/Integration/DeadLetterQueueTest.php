<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\ProcessDeadLetterMessage;
use App\Jobs\ProcessExpenseRecordedEvent;
use App\Jobs\ProcessInvoiceCreatedEvent;
use App\Services\Queue\DeadLetterQueueHandler;
use App\Services\Queue\MessageRetryService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Dead Letter Queue (DLQ) Processing Tests
 *
 * Tests handling of failed messages that exceed retry limits
 */
class DeadLetterQueueTest extends TestCase
{
    private DeadLetterQueueHandler $dlqHandler;

    private MessageRetryService $retryService;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->dlqHandler = app(DeadLetterQueueHandler::class);
        $this->retryService = app(MessageRetryService::class);
    }

    public function test_message_moved_to_dlq_after_max_retries(): void
    {
        $message = [
            'id' => 'msg-123',
            'event' => 'invoice.created',
            'data' => ['invoice_id' => 1],
            'retry_count' => 3,
            'max_retries' => 3,
        ];

        $this->dlqHandler->handleFailedMessage($message);

        // Verify message is in DLQ
        Queue::assertPushed(ProcessDeadLetterMessage::class);
    }

    public function test_dlq_message_includes_metadata(): void
    {
        $message = [
            'id' => 'msg-456',
            'event' => 'payment.received',
            'data' => ['amount' => 100],
            'error' => 'Service timeout',
            'exception' => 'TimeoutException',
            'failed_at' => now()->toIso8601String(),
        ];

        $dlqMessage = $this->dlqHandler->toDLQMessage($message);

        $this->assertEquals('msg-456', $dlqMessage['id']);
        $this->assertEquals('payment.received', $dlqMessage['event']);
        $this->assertArrayHasKey('error', $dlqMessage);
        $this->assertArrayHasKey('exception', $dlqMessage);
    }

    public function test_dlq_message_persistence(): void
    {
        $message = [
            'id' => 'msg-789',
            'event' => 'shipment.created',
            'data' => ['tracking' => 'TRACK123'],
        ];

        $this->dlqHandler->persist($message);

        // Verify persisted
        $persisted = $this->dlqHandler->retrieve('msg-789');
        $this->assertNotNull($persisted);
        $this->assertEquals('msg-789', $persisted['id']);
    }

    public function test_dlq_message_retry_from_queue(): void
    {
        $message = [
            'id' => 'msg-101',
            'event' => 'expense.recorded',
            'data' => ['amount' => 50],
            'retry_count' => 0,
        ];

        // Send to DLQ
        $this->dlqHandler->handleFailedMessage($message);

        // Attempt to requeue
        $this->dlqHandler->requeuMessage('msg-101');

        Queue::assertPushed(ProcessExpenseRecordedEvent::class);
    }

    public function test_dlq_handler_with_exponential_backoff(): void
    {
        $message = [
            'id' => 'msg-202',
            'event' => 'product.added',
            'retry_count' => 1,
        ];

        // First retry: 2^1 * 100ms = 200ms
        $firstDelay = $this->retryService->calculateDelay($message, multiplier: 2, baseDelay: 100);
        $this->assertEquals(200, $firstDelay);

        $message['retry_count'] = 2;
        // Second retry: 2^2 * 100ms = 400ms
        $secondDelay = $this->retryService->calculateDelay($message, multiplier: 2, baseDelay: 100);
        $this->assertEquals(400, $secondDelay);
    }

    public function test_dlq_message_filtering(): void
    {
        $invoiceMessage = [
            'id' => 'msg-303',
            'event' => 'invoice.created',
            'data' => ['invoice_id' => 1],
        ];

        $paymentMessage = [
            'id' => 'msg-304',
            'event' => 'payment.received',
            'data' => ['amount' => 100],
        ];

        $this->dlqHandler->persist($invoiceMessage);
        $this->dlqHandler->persist($paymentMessage);

        // Filter by event type
        $invoiceMessages = $this->dlqHandler->filterByEvent('invoice.created');

        $this->assertCount(1, $invoiceMessages);
        $this->assertEquals('msg-303', $invoiceMessages[0]['id']);
    }

    public function test_dlq_message_age_filtering(): void
    {
        // Create old message
        $oldMessage = [
            'id' => 'msg-405',
            'event' => 'invoice.created',
            'failed_at' => now()->subDays(2)->toIso8601String(),
        ];

        // Create recent message
        $recentMessage = [
            'id' => 'msg-406',
            'event' => 'invoice.created',
            'failed_at' => now()->subMinutes(5)->toIso8601String(),
        ];

        $this->dlqHandler->persist($oldMessage);
        $this->dlqHandler->persist($recentMessage);

        // Get messages older than 1 day
        $oldMessages = $this->dlqHandler->filterByAge(days: 1, older: true);

        $this->assertCount(1, $oldMessages);
    }

    public function test_dlq_message_deletion(): void
    {
        $message = [
            'id' => 'msg-507',
            'event' => 'shipment.created',
            'data' => ['tracking' => 'TRACK456'],
        ];

        $this->dlqHandler->persist($message);

        // Verify it exists
        $retrieved = $this->dlqHandler->retrieve('msg-507');
        $this->assertNotNull($retrieved);

        // Delete it
        $this->dlqHandler->delete('msg-507');

        // Verify deletion
        $deleted = $this->dlqHandler->retrieve('msg-507');
        $this->assertNull($deleted);
    }

    public function test_dlq_message_batch_deletion(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->dlqHandler->persist([
                'id' => "msg-50{$i}",
                'event' => 'test.event',
                'failed_at' => now()->subDays(30)->toIso8601String(),
            ]);
        }

        // Delete messages older than 7 days
        $deleted = $this->dlqHandler->deleteOlderThan(days: 7);

        $this->assertEquals(5, $deleted);
    }

    public function test_dlq_visibility_timeout(): void
    {
        $message = [
            'id' => 'msg-609',
            'event' => 'expense.recorded',
            'data' => ['amount' => 75],
        ];

        $this->dlqHandler->persist($message);

        // Get message with visibility timeout
        $retrieved = $this->dlqHandler->retrieveWithVisibilityTimeout('msg-609', timeout: 300);

        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved['locked'] ?? false);
    }

    public function test_dlq_message_unlock(): void
    {
        $message = [
            'id' => 'msg-710',
            'event' => 'product.added',
            'locked' => true,
        ];

        $this->dlqHandler->persist($message);

        // Unlock message
        $this->dlqHandler->unlock('msg-710');

        $retrieved = $this->dlqHandler->retrieve('msg-710');
        $this->assertFalse($retrieved['locked'] ?? false);
    }

    public function test_dlq_statistics(): void
    {
        // Add various messages
        for ($i = 1; $i <= 10; $i++) {
            $this->dlqHandler->persist([
                'id' => "msg-stats-{$i}",
                'event' => $i <= 5 ? 'invoice.created' : 'payment.received',
                'retry_count' => rand(1, 3),
            ]);
        }

        $stats = $this->dlqHandler->getStatistics();

        $this->assertArrayHasKey('total_messages', $stats);
        $this->assertArrayHasKey('by_event_type', $stats);
        $this->assertArrayHasKey('average_retry_count', $stats);
    }

    public function test_dlq_handler_callback_on_message_received(): void
    {
        $callbackFired = false;

        $message = [
            'id' => 'msg-811',
            'event' => 'invoice.created',
        ];

        $this->dlqHandler->onMessageReceived(function ($msg) use (&$callbackFired) {
            $callbackFired = true;
        });

        $this->dlqHandler->handleFailedMessage($message);

        $this->assertTrue($callbackFired);
    }

    public function test_dlq_handler_callback_on_message_requeued(): void
    {
        $callbackFired = false;

        $message = [
            'id' => 'msg-912',
            'event' => 'payment.received',
        ];

        $this->dlqHandler->onMessageRequeued(function ($msg) use (&$callbackFired) {
            $callbackFired = true;
        });

        $this->dlqHandler->persist($message);
        $this->dlqHandler->requeuMessage('msg-912');

        $this->assertTrue($callbackFired);
    }

    public function test_dlq_message_context_preservation(): void
    {
        $message = [
            'id' => 'msg-1013',
            'event' => 'shipment.created',
            'data' => ['tracking' => 'TRACK789'],
            'context' => [
                'correlation_id' => 'corr-123',
                'tenant_id' => 'tenant-1',
                'user_id' => 'user-1',
            ],
        ];

        $this->dlqHandler->persist($message);

        $retrieved = $this->dlqHandler->retrieve('msg-1013');

        $this->assertEquals('corr-123', $retrieved['context']['correlation_id']);
        $this->assertEquals('tenant-1', $retrieved['context']['tenant_id']);
    }

    public function test_dlq_bulk_processing(): void
    {
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $messages[] = [
                'id' => "msg-bulk-{$i}",
                'event' => 'test.event',
                'data' => ['index' => $i],
            ];
        }

        // Process in bulk
        $processed = $this->dlqHandler->processBulk($messages);

        $this->assertEquals(5, $processed);
    }

    public function test_dlq_replay_messages(): void
    {
        // Create messages in DLQ
        for ($i = 1; $i <= 3; $i++) {
            $this->dlqHandler->persist([
                'id' => "msg-replay-{$i}",
                'event' => 'invoice.created',
                'data' => ['invoice_id' => $i],
            ]);
        }

        // Replay all messages
        $replayed = $this->dlqHandler->replayAll();

        $this->assertEquals(3, $replayed);
        Queue::assertPushed(ProcessInvoiceCreatedEvent::class, 3);
    }

    public function test_dlq_archive_old_messages(): void
    {
        // Create old message
        $this->dlqHandler->persist([
            'id' => 'msg-archive-old',
            'event' => 'invoice.created',
            'failed_at' => now()->subDays(60)->toIso8601String(),
        ]);

        // Create recent message
        $this->dlqHandler->persist([
            'id' => 'msg-archive-new',
            'event' => 'invoice.created',
            'failed_at' => now()->subDays(5)->toIso8601String(),
        ]);

        // Archive old messages
        $archived = $this->dlqHandler->archiveOlderThan(days: 30);

        $this->assertEquals(1, $archived);

        // Old message should still be retrievable from archive
        $archivedMessage = $this->dlqHandler->retrieveFromArchive('msg-archive-old');
        $this->assertNotNull($archivedMessage);
    }

    public function test_dlq_export_messages(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->dlqHandler->persist([
                'id' => "msg-export-{$i}",
                'event' => 'test.event',
            ]);
        }

        $exported = $this->dlqHandler->exportAsJson();

        $this->assertIsString($exported);
        $decoded = json_decode($exported, true);
        $this->assertCount(3, $decoded);
    }
}
