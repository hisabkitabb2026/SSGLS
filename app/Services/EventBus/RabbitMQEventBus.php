<?php

namespace App\Services\EventBus;

use App\Services\EventBus\Contracts\EventBusContract;
use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Tracing\TracingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * RabbitMQ Event Bus Implementation
 *
 * Implements event-driven architecture using RabbitMQ as the message broker
 */
class RabbitMQEventBus implements EventBusContract
{
    protected AMQPStreamConnection $connection;

    protected AMQPChannel $channel;

    protected array $handlers = [];

    protected TracingService $tracing;

    public function __construct(
        protected array $config = [],
        ?TracingService $tracing = null,
    ) {
        $this->config = config('event-bus');
        $this->tracing = $tracing ?? app(TracingService::class);
        $this->connect();
    }

    /**
     * Establish connection to RabbitMQ
     */
    protected function connect(): void
    {
        $rabbitmqConfig = $this->config['broker']['rabbitmq'];

        $this->connection = new AMQPStreamConnection(
            $rabbitmqConfig['host'],
            $rabbitmqConfig['port'],
            $rabbitmqConfig['username'],
            $rabbitmqConfig['password'],
            $rabbitmqConfig['vhost'],
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $rabbitmqConfig['timeout'],
            $rabbitmqConfig['heartbeat'],
        );

        $this->channel = $this->connection->channel();
        $this->declareExchangesAndQueues();
    }

    /**
     * Declare exchanges, queues, and bindings
     */
    protected function declareExchangesAndQueues(): void
    {
        $eventExchange = $this->config['events']['exchange'];
        $eventQueue = $this->config['events']['queue'];

        // Declare main event exchange
        $this->channel->exchange_declare(
            $eventExchange['name'],
            $eventExchange['type'],
            $eventExchange['durable'] ?? true,
            $eventExchange['auto_delete'] ?? false,
            false,
            false,
            false,
            $eventExchange['arguments'] ?? []
        );

        // Declare dead letter exchange
        if ($this->config['dead_letter']['enabled'] ?? false) {
            $dlx = $this->config['dead_letter']['exchange'];
            $this->channel->exchange_declare(
                $dlx['name'],
                $dlx['type'],
                $dlx['durable'] ?? true,
                $dlx['auto_delete'] ?? false,
            );

            // Declare DLQ
            $dlq = $this->config['dead_letter']['queue'];
            $this->channel->queue_declare(
                $dlq['name'],
                false,
                $dlq['durable'] ?? true,
                false,
                false,
                false,
                ['x-message-ttl' => 86400000]
            );

            $this->channel->queue_bind(
                $dlq['name'],
                $dlx['name'],
                '#'
            );
        }
    }

    /**
     * Publish an event to the message broker
     */
    public function publish(Event $event, array $metadata = []): string
    {
        $span = $this->tracing->startSpan('event.publish', [
            'event_type' => $event::class,
            'aggregate_type' => $event->aggregateType,
        ]);

        try {
            $eventData = $event->toArray();
            $eventData['metadata'] = $metadata;
            $eventData['published_at'] = now()->toIso8601String();

            // Log the event
            $this->logEvent('published', $event, $eventData);

            // Store event for event sourcing
            $this->storeEvent($event, $eventData);

            // Prepare AMQP message
            $message = new AMQPMessage(
                json_encode($eventData, JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $event->id,
                    'correlation_id' => $event->correlationId,
                    'timestamp' => $event->timestamp,
                    'headers' => [
                        'x-event-type' => $event::class,
                        'x-aggregate-type' => $event->aggregateType,
                        'x-aggregate-id' => $event->aggregateId,
                        'x-correlation-id' => $event->correlationId,
                        'x-idempotency-key' => $event->idempotencyKey,
                    ],
                ]
            );

            // Publish to event exchange
            $this->channel->basic_publish(
                $message,
                $this->config['events']['exchange']['name'],
                $event->getRoutingKey()
            );

            $span->end();

            return $event->id;
        } catch (\Exception $e) {
            Log::error('Failed to publish event', [
                'event_id' => $event->id,
                'event_type' => $event::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $span->recordException($e);
            throw $e;
        }
    }

    /**
     * Subscribe to events with a specific pattern
     */
    public function subscribe(string $pattern, callable $handler, ?string $queue = null): void
    {
        $queue = $queue ?? sprintf('invoiceshelf.%s', str_replace('.*', '', $pattern));

        $this->handlers[$pattern] = $handler;

        // Declare queue
        $this->channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            array_merge(
                $this->config['events']['queue'],
                ['x-dead-letter-exchange' => $this->config['dead_letter']['exchange']['name'] ?? 'invoiceshelf.dlx']
            )
        );

        // Bind queue to exchange with pattern
        $this->channel->queue_bind(
            $queue,
            $this->config['events']['exchange']['name'],
            $pattern
        );

        Log::info("Subscribed to event pattern: {$pattern}");
    }

    /**
     * Unsubscribe from events
     */
    public function unsubscribe(string $pattern, ?string $queue = null): void
    {
        $queue = $queue ?? sprintf('invoiceshelf.%s', str_replace('.*', '', $pattern));

        $this->channel->queue_unbind($queue, $this->config['events']['exchange']['name'], $pattern);
        unset($this->handlers[$pattern]);

        Log::info("Unsubscribed from event pattern: {$pattern}");
    }

    /**
     * Start listening to events
     */
    public function listen(?string $queue = null, int $timeout = 0): void
    {
        $queue = $queue ?? 'invoiceshelf.events';

        Log::info("Starting event listener on queue: {$queue}");

        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) {
                $this->handleMessage($message);
            }
        );

        try {
            $this->channel->consume(null, $timeout);
        } catch (\Exception $e) {
            Log::error("Error consuming messages: {$e->getMessage()}");
        }
    }

    /**
     * Handle incoming message
     */
    protected function handleMessage(AMQPMessage $message): void
    {
        $span = $this->tracing->startSpan('event.handle', [
            'message_id' => $message->get('message_id'),
        ]);

        try {
            $eventData = json_decode($message->body, true, flags: JSON_THROW_ON_ERROR);

            // Check idempotency
            if (! $this->isIdempotent($eventData)) {
                Log::warning("Duplicate event detected, skipping: {$eventData['id']}");
                $message->ack();

                return;
            }

            // Find matching handler
            foreach ($this->handlers as $pattern => $handler) {
                if ($this->matchesPattern($eventData['id'], $pattern)) {
                    $handler($eventData);
                    $this->markAsProcessed($eventData['id']);
                    break;
                }
            }

            $message->ack();
        } catch (\Exception $e) {
            Log::error("Error handling message: {$e->getMessage()}", [
                'message_id' => $message->get('message_id'),
                'error' => $e->getMessage(),
            ]);

            $span->recordException($e);
            $message->nack(true); // Requeue
        } finally {
            $span->end();
        }
    }

    /**
     * Get event metadata
     */
    public function getMetadata(string $eventId): array
    {
        return DB::table('event_logs')
            ->where('id', $eventId)
            ->first()?->metadata ?? [];
    }

    /**
     * Replay events from a specific time
     */
    public function replay(\DateTime $from, ?\DateTime $to = null, ?string $pattern = null): void
    {
        $query = DB::table('event_logs')
            ->where('published_at', '>=', $from);

        if ($to) {
            $query->where('published_at', '<=', $to);
        }

        if ($pattern) {
            $query->where('event_type', 'like', $pattern);
        }

        $events = $query->orderBy('published_at')->get();

        foreach ($events as $eventRecord) {
            $eventData = json_decode($eventRecord->payload, true);
            // Handle replay
            foreach ($this->handlers as $pattern => $handler) {
                if ($this->matchesPattern($eventRecord->event_type, $pattern)) {
                    $handler($eventData);
                    break;
                }
            }
        }
    }

    /**
     * Check if event is idempotent (not processed before)
     */
    protected function isIdempotent(array $eventData): bool
    {
        if (! $this->config['idempotency']['enabled'] ?? false) {
            return true;
        }

        if (! $eventData['idempotency_key'] ?? null) {
            return true;
        }

        $existing = DB::table('idempotent_events')
            ->where('idempotency_key', $eventData['idempotency_key'])
            ->exists();

        if ($existing) {
            return false;
        }

        // Record the idempotent key
        DB::table('idempotent_events')->insert([
            'idempotency_key' => $eventData['idempotency_key'],
            'event_id' => $eventData['id'],
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Log event
     */
    protected function logEvent(string $action, Event $event, array $eventData): void
    {
        if (! ($this->config['logging']['enabled'] ?? true)) {
            return;
        }

        Log::channel($this->config['logging']['channel'] ?? 'single')
            ->log(
                $this->config['logging']['log_level'] ?? 'info',
                "Event {$action}: ".$event::class,
                [
                    'event_id' => $event->id,
                    'aggregate_id' => $event->aggregateId,
                    'correlation_id' => $event->correlationId,
                    'payload' => $this->config['logging']['log_payload'] ?? false ? $eventData : null,
                ]
            );
    }

    /**
     * Store event for event sourcing
     */
    protected function storeEvent(Event $event, array $eventData): void
    {
        DB::table('event_logs')->insert([
            'id' => $event->id,
            'event_type' => $event::class,
            'aggregate_type' => $event->aggregateType,
            'aggregate_id' => $event->aggregateId,
            'correlation_id' => $event->correlationId,
            'causation_id' => $event->causationId,
            'user_id' => $event->userId,
            'company_id' => $event->companyId,
            'payload' => json_encode($eventData),
            'metadata' => json_encode($eventData['metadata'] ?? []),
            'published_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Mark event as processed
     */
    protected function markAsProcessed(string $eventId): void
    {
        DB::table('event_logs')
            ->where('id', $eventId)
            ->update(['is_processed' => true, 'processed_at' => now()]);
    }

    /**
     * Match event pattern
     */
    protected function matchesPattern(string $eventId, string $pattern): bool
    {
        // Convert routing key pattern to regex
        $regex = str_replace('*', '[^.]*', $pattern);
        $regex = str_replace('#', '.*', $regex);

        return preg_match("~^{$regex}$~", $eventId) === 1;
    }

    /**
     * Cleanup old events and processed records
     */
    public function cleanup(int $daysOld = 30): int
    {
        $date = now()->subDays($daysOld);

        $deleted = DB::table('event_logs')
            ->where('created_at', '<', $date)
            ->where('is_processed', true)
            ->delete();

        Log::info("Cleaned up {$deleted} old event logs");

        return $deleted;
    }

    /**
     * Disconnect from broker
     */
    public function disconnect(): void
    {
        if (isset($this->channel) && $this->channel->is_open()) {
            $this->channel->close();
        }

        if (isset($this->connection) && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
