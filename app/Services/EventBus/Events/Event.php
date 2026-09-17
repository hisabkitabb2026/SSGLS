<?php

namespace App\Services\EventBus\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Ramsey\Uuid\Uuid;

/**
 * Base Event Class
 *
 * All domain events should extend this class to ensure proper handling
 */
abstract class Event
{
    use Dispatchable, SerializesModels;

    /**
     * Unique event identifier
     */
    public string $id;

    /**
     * Event version for schema evolution
     */
    public int $version = 1;

    /**
     * Timestamp when the event occurred
     */
    public int $timestamp;

    /**
     * Correlation ID for tracing related events
     */
    public string $correlationId;

    /**
     * Causation ID for linking cause and effect
     */
    public string $causationId;

    /**
     * User ID who triggered the event
     */
    public ?string $userId;

    /**
     * Company ID for multi-tenancy
     */
    public ?string $companyId;

    /**
     * Aggregate ID that this event belongs to
     */
    public string $aggregateId;

    /**
     * Aggregate type (e.g., Invoice, Expense)
     */
    public string $aggregateType;

    /**
     * Whether event has been processed
     */
    public bool $isProcessed = false;

    /**
     * Idempotency key for duplicate detection
     */
    public ?string $idempotencyKey = null;

    /**
     * Create a new Event instance
     */
    public function __construct(
        string $aggregateId,
        string $aggregateType,
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->timestamp = now()->timestamp;
        $this->correlationId = request()->header('X-Correlation-ID') ?? Uuid::uuid4()->toString();
        $this->causationId = request()->header('X-Causation-ID') ?? $this->id;
        $this->userId = $userId ?? auth()->id();
        $this->companyId = $companyId ?? request()->header('company');
        $this->aggregateId = $aggregateId;
        $this->aggregateType = $aggregateType;
        $this->idempotencyKey = request()->header('X-Idempotency-Key');
    }

    /**
     * Get the event name/topic
     */
    public function getName(): string
    {
        $className = class_basename(static::class);
        // Convert PascalCase to snake.case for AMQP topic
        $name = preg_replace('/(?<!^)[A-Z]/', '.$0', $className);

        return strtolower($name);
    }

    /**
     * Get the routing key for the message broker
     */
    public function getRoutingKey(): string
    {
        return sprintf(
            '%s.%s.%s',
            strtolower($this->aggregateType),
            $this->getName(),
            hash('md5', $this->aggregateId)
        );
    }

    /**
     * Convert event to array for serialization
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => static::class,
            'version' => $this->version,
            'timestamp' => $this->timestamp,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'user_id' => $this->userId,
            'company_id' => $this->companyId,
            'aggregate_id' => $this->aggregateId,
            'aggregate_type' => $this->aggregateType,
            'idempotency_key' => $this->idempotencyKey,
            'payload' => $this->getPayload(),
        ];
    }

    /**
     * Get the event payload (to be implemented by subclasses)
     */
    protected function getPayload(): array
    {
        $payload = [];
        foreach ((new \ReflectionClass($this))->getProperties() as $property) {
            if (in_array($property->getName(), [
                'id', 'version', 'timestamp', 'correlationId', 'causationId',
                'userId', 'companyId', 'aggregateId', 'aggregateType', 'isProcessed',
                'idempotencyKey', 'connection', 'queue',
            ])) {
                continue;
            }
            $property->setAccessible(true);
            $payload[$property->getName()] = $property->getValue($this);
        }

        return $payload;
    }

    /**
     * Reconstruct event from array
     */
    public static function fromArray(array $data): static
    {
        $event = new static(
            $data['aggregate_id'],
            $data['aggregate_type'],
            $data['user_id'] ?? null,
            $data['company_id'] ?? null,
        );

        $event->id = $data['id'];
        $event->version = $data['version'] ?? 1;
        $event->timestamp = $data['timestamp'];
        $event->correlationId = $data['correlation_id'];
        $event->causationId = $data['causation_id'];
        $event->isProcessed = $data['is_processed'] ?? false;
        $event->idempotencyKey = $data['idempotency_key'] ?? null;

        return $event;
    }
}
