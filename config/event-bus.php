<?php

/**
 * Event Bus Configuration
 *
 * This configuration defines the event bus system for event-driven architecture
 * with RabbitMQ message broker, sagas, and distributed transaction handling.
 */

return [
    /**
     * Message Broker Configuration
     */
    'broker' => [
        'driver' => env('EVENT_BUS_BROKER', 'rabbitmq'),

        'rabbitmq' => [
            'host' => env('RABBITMQ_HOST', 'rabbitmq'),
            'port' => env('RABBITMQ_PORT', 5672),
            'username' => env('RABBITMQ_USER', 'invoiceshelf'),
            'password' => env('RABBITMQ_PASSWORD', 'rabbitmq_password'),
            'vhost' => env('RABBITMQ_VHOST', '/invoiceshelf'),
            'timeout' => env('RABBITMQ_TIMEOUT', 30),
            'heartbeat' => env('RABBITMQ_HEARTBEAT', 60),
        ],
    ],

    /**
     * Event Configuration
     */
    'events' => [
        // Exchange settings
        'exchange' => [
            'name' => env('EVENT_BUS_EXCHANGE', 'invoiceshelf.events'),
            'type' => 'topic',
            'durable' => true,
            'auto_delete' => false,
            'arguments' => [
                'x-max-length' => 1000000,
            ],
        ],

        // Default queue settings
        'queue' => [
            'durable' => true,
            'auto_delete' => false,
            'exclusive' => false,
            'passive' => false,
            'arguments' => [
                'x-message-ttl' => 86400000, // 24 hours
                'x-max-length' => 100000,
                'x-dead-letter-exchange' => 'invoiceshelf.dlx',
            ],
        ],
    ],

    /**
     * Dead Letter Queue Configuration
     */
    'dead_letter' => [
        'enabled' => env('EVENT_BUS_DLQ_ENABLED', true),
        'exchange' => [
            'name' => env('EVENT_BUS_DLX_EXCHANGE', 'invoiceshelf.dlx'),
            'type' => 'topic',
            'durable' => true,
        ],
        'queue' => [
            'name' => env('EVENT_BUS_DLQ_QUEUE', 'invoiceshelf.dlq'),
            'durable' => true,
            'max_retries' => env('EVENT_BUS_DLQ_MAX_RETRIES', 5),
            'retention_days' => env('EVENT_BUS_DLQ_RETENTION_DAYS', 30),
        ],
    ],

    /**
     * Retry Configuration
     */
    'retry' => [
        'enabled' => env('EVENT_BUS_RETRY_ENABLED', true),
        'max_attempts' => env('EVENT_BUS_RETRY_MAX_ATTEMPTS', 5),
        'initial_delay' => env('EVENT_BUS_RETRY_INITIAL_DELAY', 1000), // milliseconds
        'max_delay' => env('EVENT_BUS_RETRY_MAX_DELAY', 300000), // 5 minutes
        'multiplier' => env('EVENT_BUS_RETRY_MULTIPLIER', 2),
        'jitter' => env('EVENT_BUS_RETRY_JITTER', true),
    ],

    /**
     * Saga Configuration
     */
    'saga' => [
        'enabled' => env('EVENT_BUS_SAGA_ENABLED', true),
        'timeout' => env('EVENT_BUS_SAGA_TIMEOUT', 3600), // 1 hour in seconds
        'storage' => env('EVENT_BUS_SAGA_STORAGE', 'database'),
    ],

    /**
     * Tracing Configuration
     */
    'tracing' => [
        'enabled' => env('EVENT_BUS_TRACING_ENABLED', true),
        'driver' => env('EVENT_BUS_TRACING_DRIVER', 'jaeger'),

        'jaeger' => [
            'host' => env('JAEGER_HOST', 'localhost'),
            'port' => env('JAEGER_PORT', 6831),
            'service_name' => env('JAEGER_SERVICE_NAME', 'invoiceshelf-event-bus'),
            'sampler' => [
                'type' => env('JAEGER_SAMPLER_TYPE', 'const'),
                'param' => env('JAEGER_SAMPLER_PARAM', 1),
            ],
        ],
    ],

    /**
     * Handler Configuration
     */
    'handlers' => [
        'async' => env('EVENT_BUS_ASYNC_HANDLERS', true),
        'timeout' => env('EVENT_BUS_HANDLER_TIMEOUT', 30),
    ],

    /**
     * Idempotency Configuration
     */
    'idempotency' => [
        'enabled' => env('EVENT_BUS_IDEMPOTENCY_ENABLED', true),
        'storage' => env('EVENT_BUS_IDEMPOTENCY_STORAGE', 'database'),
        'ttl' => env('EVENT_BUS_IDEMPOTENCY_TTL', 86400), // 24 hours in seconds
    ],

    /**
     * Monitoring Configuration
     */
    'monitoring' => [
        'enabled' => env('EVENT_BUS_MONITORING_ENABLED', true),
        'metrics' => [
            'enabled' => env('EVENT_BUS_METRICS_ENABLED', true),
            'prefix' => 'invoiceshelf_events',
        ],
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'enabled' => env('EVENT_BUS_LOGGING_ENABLED', true),
        'channel' => env('EVENT_BUS_LOG_CHANNEL', 'single'),
        'log_level' => env('EVENT_BUS_LOG_LEVEL', 'info'),
        'log_payload' => env('EVENT_BUS_LOG_PAYLOAD', false),
    ],
];
