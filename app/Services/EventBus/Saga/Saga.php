<?php

namespace App\Services\EventBus\Saga;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Tracing\TracingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * Saga Base Class
 *
 * Implements the Saga pattern for managing distributed transactions across domains
 */
abstract class Saga
{
    /**
     * Saga ID
     */
    public string $id;

    /**
     * Saga state
     */
    protected string $state = 'started';

    /**
     * Compensation steps to execute on failure
     */
    protected array $compensationSteps = [];

    /**
     * Events that triggered this saga
     */
    protected array $events = [];

    /**
     * Saga definition
     */
    protected array $definition = [];

    /**
     * Create new Saga instance
     */
    public function __construct(string $id = '')
    {
        $this->id = $id ?: Uuid::uuid4()->toString();
        $this->definition = static::define();
    }

    /**
     * Define the saga flow
     *
     * @return array Saga definition
     */
    abstract public static function define(): array;

    /**
     * Start the saga
     */
    public function start(Event $event): void
    {
        try {
            Log::info('Starting saga: '.static::class, ['saga_id' => $this->id]);

            // Store saga in database
            $this->storeSaga('started');

            // Execute initial step
            $this->processEvent($event);

            // Update state
            $this->setState('running');
        } catch (\Exception $e) {
            Log::error('Error starting saga', [
                'saga_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            $this->rollback();
        }
    }

    /**
     * Process incoming event
     */
    public function processEvent(Event $event): void
    {
        $this->events[] = $event;

        // Find matching handler in definition
        foreach ($this->definition['steps'] as $step) {
            if ($this->matchesEvent($event, $step['triggerOn'])) {
                $this->executeStep($step, $event);
            }
        }
    }

    /**
     * Execute a saga step
     */
    protected function executeStep(array $step, Event $event): void
    {
        $span = app(TracingService::class)
            ->startSpan('saga.step', [
                'saga_id' => $this->id,
                'step_name' => $step['name'],
                'event_type' => $event::class,
            ]);

        try {
            // Execute the action
            if (is_callable($step['action'])) {
                call_user_func($step['action'], $event, $this);
            }

            // Store compensation step
            if ($step['compensate'] ?? null) {
                $this->compensationSteps[] = [
                    'action' => $step['compensate'],
                    'context' => [
                        'event' => $event,
                        'step' => $step,
                    ],
                ];
            }

            // Check if saga should end
            if ($step['endsSaga'] ?? false) {
                $this->complete();
            }

            $span->end();
        } catch (\Exception $e) {
            Log::error('Error executing saga step', [
                'saga_id' => $this->id,
                'step' => $step['name'],
                'error' => $e->getMessage(),
            ]);

            $span->recordException($e);
            $this->rollback();
        }
    }

    /**
     * Rollback saga (execute compensation)
     */
    public function rollback(): void
    {
        Log::warning('Rolling back saga', ['saga_id' => $this->id]);

        $this->setState('rolling_back');

        // Execute compensation steps in reverse order
        foreach (array_reverse($this->compensationSteps) as $step) {
            try {
                if (is_callable($step['action'])) {
                    call_user_func($step['action'], $step['context']);
                }
            } catch (\Exception $e) {
                Log::error('Error executing compensation step', [
                    'saga_id' => $this->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->setState('rolled_back');
    }

    /**
     * Complete the saga
     */
    protected function complete(): void
    {
        Log::info('Saga completed', ['saga_id' => $this->id]);
        $this->setState('completed');
    }

    /**
     * Check if event matches saga trigger
     */
    protected function matchesEvent(Event $event, string $eventPattern): bool
    {
        if ($eventPattern === '*') {
            return true;
        }

        return str_contains($event::class, $eventPattern)
            || str_contains($event->getName(), $eventPattern);
    }

    /**
     * Set saga state
     */
    protected function setState(string $state): void
    {
        $this->state = $state;

        DB::table('sagas')
            ->where('id', $this->id)
            ->update([
                'state' => $state,
                'updated_at' => now(),
            ]);
    }

    /**
     * Store saga in database
     */
    protected function storeSaga(string $state): void
    {
        DB::table('sagas')->insert([
            'id' => $this->id,
            'type' => static::class,
            'state' => $state,
            'data' => json_encode([
                'events' => $this->events,
                'compensation_steps' => count($this->compensationSteps),
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get saga from database
     */
    public static function retrieve(string $id): ?static
    {
        $record = DB::table('sagas')
            ->where('id', $id)
            ->first();

        if (! $record) {
            return null;
        }

        $saga = new static($id);
        $saga->state = $record->state;

        return $saga;
    }

    /**
     * Get current state
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get events
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Timeout check
     */
    public function checkTimeout(): void
    {
        $saga = DB::table('sagas')
            ->where('id', $this->id)
            ->first();

        if (! $saga) {
            return;
        }

        $timeout = config('event-bus.saga.timeout', 3600);
        $createdAt = strtotime($saga->created_at);
        $elapsed = time() - $createdAt;

        if ($elapsed > $timeout) {
            Log::warning('Saga timeout', ['saga_id' => $this->id]);
            $this->rollback();
        }
    }
}
