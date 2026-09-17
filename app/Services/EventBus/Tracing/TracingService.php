<?php

namespace App\Services\EventBus\Tracing;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\Tracer;
use OpenTelemetry\Contrib\Jaeger\Exporter as JaegerExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * Tracing Service with Jaeger Integration
 *
 * Provides distributed tracing for event processing using OpenTelemetry and Jaeger
 */
class TracingService
{
    protected Tracer $tracer;

    protected array $config;

    protected array $spans = [];

    protected int $spanCounter = 0;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: config('event-bus.tracing', []);

        if ($this->config['enabled'] ?? false) {
            $this->initializeTracer();
        }
    }

    /**
     * Initialize the tracer with Jaeger exporter
     */
    protected function initializeTracer(): void
    {
        try {
            $jaegerConfig = $this->config['jaeger'] ?? [];

            // Create Jaeger exporter
            $exporter = new JaegerExporter(
                $jaegerConfig['service_name'] ?? 'invoiceshelf-event-bus',
                $jaegerConfig['host'] ?? 'localhost',
                $jaegerConfig['port'] ?? 6831,
            );

            // Create tracer provider with span processor
            $provider = new TracerProvider;
            $provider->addSpanProcessor(new SimpleSpanProcessor($exporter));

            // Get tracer instance
            $this->tracer = $provider->getTracer('event-bus');

            Log::info('Jaeger tracing initialized', [
                'service' => $jaegerConfig['service_name'] ?? 'invoiceshelf-event-bus',
                'host' => $jaegerConfig['host'] ?? 'localhost',
                'port' => $jaegerConfig['port'] ?? 6831,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to initialize Jaeger tracing: '.$e->getMessage());
        }
    }

    /**
     * Start a new span
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = $this->tracer->spanBuilder($name)
            ->startSpan();

        // Add attributes
        foreach ($attributes as $key => $value) {
            $span->setAttribute($key, $value);
        }

        // Add context attributes
        $span->setAttribute('correlation_id', request()?->header('X-Correlation-ID') ?? 'unknown');
        $span->setAttribute('user_id', auth()?->id() ?? 'anonymous');
        $span->setAttribute('company_id', request()?->header('company') ?? 'unknown');

        // Track span
        $spanId = ++$this->spanCounter;
        $this->spans[$spanId] = $span;

        return $span;
    }

    /**
     * Get current span
     */
    public function getCurrentSpan(): ?SpanInterface
    {
        return end($this->spans) ?: null;
    }

    /**
     * Add tag to current span
     */
    public function addTag(string $key, mixed $value): void
    {
        $span = $this->getCurrentSpan();
        if ($span) {
            $span->setAttribute($key, $value);
        }
    }

    /**
     * Log message to current span
     */
    public function logMessage(string $message, array $context = []): void
    {
        $span = $this->getCurrentSpan();
        if ($span) {
            $span->addEvent($message, $context);
        }
    }

    /**
     * Record exception in span
     */
    public function recordException(\Exception $exception, array $context = []): void
    {
        $span = $this->getCurrentSpan();
        if ($span) {
            $span->recordException($exception);
            foreach ($context as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }
    }

    /**
     * Set span status
     */
    public function setStatus(string $status, string $description = ''): void
    {
        $span = $this->getCurrentSpan();
        if ($span) {
            $span->setStatus($status, $description);
        }
    }

    /**
     * Inject trace context into headers
     */
    public function injectContext(): array
    {
        $span = $this->getCurrentSpan();
        if (! $span) {
            return [];
        }

        return [
            'X-Trace-ID' => $span->getSpanContext()->getTraceId(),
            'X-Span-ID' => $span->getSpanContext()->getSpanId(),
            'X-Parent-ID' => $span->getSpanContext()->getSpanId(),
        ];
    }

    /**
     * Extract trace context from headers
     */
    public function extractContext(array $headers): array
    {
        return [
            'trace_id' => $headers['X-Trace-ID'] ?? $headers['x-trace-id'] ?? null,
            'span_id' => $headers['X-Span-ID'] ?? $headers['x-span-id'] ?? null,
            'parent_id' => $headers['X-Parent-ID'] ?? $headers['x-parent-id'] ?? null,
        ];
    }

    /**
     * Get all spans
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * Clear spans
     */
    public function clearSpans(): void
    {
        $this->spans = [];
        $this->spanCounter = 0;
    }

    /**
     * Shutdown tracer
     */
    public function shutdown(): void
    {
        // Flush any pending spans
        if (isset($this->tracer)) {
            // OpenTelemetry SDK handles shutdown
        }
    }

    public function __destruct()
    {
        $this->shutdown();
    }
}
