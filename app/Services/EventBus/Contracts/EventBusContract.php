<?php

namespace App\Services\EventBus\Contracts;

use App\Services\EventBus\Events\Event;

/**
 * EventBus Contract
 *
 * Defines the interface for event bus operations
 */
interface EventBusContract
{
    /**
     * Publish an event to the message broker
     *
     * @param  array<string, mixed>  $metadata
     * @return string Event ID
     */
    public function publish(Event $event, array $metadata = []): string;

    /**
     * Subscribe to events with a specific pattern
     */
    public function subscribe(string $pattern, callable $handler, ?string $queue = null): void;

    /**
     * Unsubscribe from events
     */
    public function unsubscribe(string $pattern, ?string $queue = null): void;

    /**
     * Start listening to events
     */
    public function listen(?string $queue = null, int $timeout = 0): void;

    /**
     * Get event metadata
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $eventId): array;

    /**
     * Replay events from a specific time
     */
    public function replay(\DateTime $from, ?\DateTime $to = null, ?string $pattern = null): void;
}
