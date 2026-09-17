<?php

namespace App\Services\EventBus\Handlers;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Retry\RetryPolicy;
use App\Services\EventBus\Tracing\TracingService;
use Illuminate\Support\Facades\Log;

/**
 * Base Event Handler Abstract Class
 *
 * All event handlers should extend this class
 */
abstract class EventHandler
{
    protected RetryPolicy $retryPolicy;

    protected TracingService $tracing;

    protected int $maxAttempts = 5;

    protected array $retryBackoff = [1, 2, 4, 8, 16]; // seconds

    public function __construct(
        ?RetryPolicy $retryPolicy = null,
        ?TracingService $tracing = null,
    ) {
        $this->retryPolicy = $retryPolicy ?? app(RetryPolicy::class);
        $this->tracing = $tracing ?? app(TracingService::class);
    }

    /**
     * Handle the event
     *
     * @throws \Exception
     */
    public function handle(Event $event): void
    {
        $span = $this->tracing->startSpan('event_handler.execute', [
            'handler' => static::class,
            'event_type' => $event::class,
            'event_id' => $event->id,
        ]);

        try {
            $this->process($event);
            $span->end();
        } catch (\Exception $e) {
            Log::error("Error in event handler: {$e->getMessage()}", [
                'handler' => static::class,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $span->recordException($e);
            throw $e;
        }
    }

    /**
     * Process the event (to be implemented by subclasses)
     */
    abstract public function process(Event $event): void;

    /**
     * Get the event patterns this handler subscribes to
     *
     * @return array<string> Event routing patterns
     */
    abstract public static function subscribe(): array;

    /**
     * Determine if this handler should retry on failure
     */
    public function shouldRetry(\Exception $exception): bool
    {
        return true;
    }

    /**
     * Get retry delay for attempt number
     */
    public function getRetryDelay(int $attempt): int
    {
        if ($attempt <= count($this->retryBackoff)) {
            return $this->retryBackoff[$attempt - 1];
        }

        // Use exponential backoff for attempts beyond the array
        return intval($this->retryBackoff[count($this->retryBackoff) - 1] * pow(2, $attempt - count($this->retryBackoff)));
    }

    /**
     * Get maximum number of retry attempts
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Called when handler fails after all retries
     */
    public function failed(Event $event, \Exception $exception): void
    {
        Log::error('Event handler failed after retries', [
            'handler' => static::class,
            'event_id' => $event->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Called before retrying the handler
     */
    public function retrying(Event $event, int $attempt): void
    {
        Log::warning('Retrying event handler', [
            'handler' => static::class,
            'event_id' => $event->id,
            'attempt' => $attempt,
        ]);
    }
}
