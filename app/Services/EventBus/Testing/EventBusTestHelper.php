<?php

namespace App\Services\EventBus\Testing;

use App\Services\EventBus\Contracts\EventBusContract;
use App\Services\EventBus\Events\Event;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Event Bus Testing Helper
 *
 * Provides utilities for testing event-driven functionality
 */
class EventBusTestHelper
{
    protected array $publishedEvents = [];

    protected array $subscribedHandlers = [];

    /**
     * Assert event was published
     */
    public static function assertEventPublished(string $eventClass, ?callable $callback = null): void
    {
        $published = DB::table('event_logs')
            ->where('event_type', $eventClass)
            ->exists();

        $message = "Event {$eventClass} was not published";

        if ($callback && $published) {
            $event = DB::table('event_logs')
                ->where('event_type', $eventClass)
                ->latest()
                ->first();

            $payload = json_decode($event->payload, true);
            $callback($payload);
        }

        Assert::assertTrue($published, $message);
    }

    /**
     * Assert event was NOT published
     */
    public static function assertEventNotPublished(string $eventClass): void
    {
        $published = DB::table('event_logs')
            ->where('event_type', $eventClass)
            ->exists();

        Assert::assertFalse(
            $published,
            "Event {$eventClass} was published but should not have been"
        );
    }

    /**
     * Assert event was processed
     */
    public static function assertEventProcessed(string $eventId): void
    {
        $processed = DB::table('event_logs')
            ->where('id', $eventId)
            ->where('is_processed', true)
            ->exists();

        Assert::assertTrue(
            $processed,
            "Event {$eventId} was not processed"
        );
    }

    /**
     * Get published events
     */
    public static function getPublishedEvents(?string $eventClass = null): array
    {
        $query = DB::table('event_logs');

        if ($eventClass) {
            $query->where('event_type', $eventClass);
        }

        return $query
            ->latest('published_at')
            ->get()
            ->map(fn ($event) => json_decode($event->payload, true))
            ->toArray();
    }

    /**
     * Get last published event
     */
    public static function getLastPublishedEvent(?string $eventClass = null): ?array
    {
        $query = DB::table('event_logs');

        if ($eventClass) {
            $query->where('event_type', $eventClass);
        }

        $event = $query->latest('published_at')->first();

        return $event ? json_decode($event->payload, true) : null;
    }

    /**
     * Count published events
     */
    public static function countPublishedEvents(?string $eventClass = null): int
    {
        $query = DB::table('event_logs');

        if ($eventClass) {
            $query->where('event_type', $eventClass);
        }

        return $query->count();
    }

    /**
     * Clear published events
     */
    public static function clearPublishedEvents(?string $eventClass = null): void
    {
        $query = DB::table('event_logs');

        if ($eventClass) {
            $query->where('event_type', $eventClass);
        }

        $query->delete();
    }

    /**
     * Assert saga was created
     */
    public static function assertSagaCreated(string $sagaClass): void
    {
        $exists = DB::table('sagas')
            ->where('type', $sagaClass)
            ->exists();

        Assert::assertTrue(
            $exists,
            "Saga {$sagaClass} was not created"
        );
    }

    /**
     * Assert saga completed
     */
    public static function assertSagaCompleted(string $sagaId): void
    {
        $saga = DB::table('sagas')
            ->where('id', $sagaId)
            ->first();

        Assert::assertNotNull($saga, "Saga {$sagaId} not found");
        Assert::assertEquals(
            'completed',
            $saga->state,
            "Saga {$sagaId} was not completed"
        );
    }

    /**
     * Assert saga rolled back
     */
    public static function assertSagaRolledBack(string $sagaId): void
    {
        $saga = DB::table('sagas')
            ->where('id', $sagaId)
            ->first();

        Assert::assertNotNull($saga, "Saga {$sagaId} not found");
        Assert::assertEquals(
            'rolled_back',
            $saga->state,
            "Saga {$sagaId} was not rolled back"
        );
    }

    /**
     * Assert message in dead letter queue
     */
    public static function assertMessageInDLQ(string $eventId): void
    {
        $inDLQ = DB::table('dead_letter_messages')
            ->where('event_id', $eventId)
            ->exists();

        Assert::assertTrue(
            $inDLQ,
            "Event {$eventId} was not found in DLQ"
        );
    }

    /**
     * Get dead letter messages
     */
    public static function getDeadLetterMessages(?string $eventType = null): array
    {
        $query = DB::table('dead_letter_messages');

        if ($eventType) {
            $query->where('event_type', $eventType);
        }

        return $query
            ->latest('created_at')
            ->get()
            ->map(fn ($msg) => json_decode($msg->payload, true))
            ->toArray();
    }

    /**
     * Mock EventBus for testing
     */
    public static function mockEventBus(): void
    {
        $mock = \Mockery::mock(EventBusContract::class);

        $mock->shouldReceive('publish')
            ->andReturnUsing(function (Event $event) {
                DB::table('event_logs')->insert([
                    'id' => $event->id,
                    'event_type' => $event::class,
                    'aggregate_type' => $event->aggregateType,
                    'aggregate_id' => $event->aggregateId,
                    'correlation_id' => $event->correlationId,
                    'causation_id' => $event->causationId,
                    'user_id' => $event->userId,
                    'company_id' => $event->companyId,
                    'payload' => json_encode($event->toArray()),
                    'published_at' => now(),
                    'created_at' => now(),
                ]);

                return $event->id;
            });

        app()->instance('event-bus', $mock);
    }
}
