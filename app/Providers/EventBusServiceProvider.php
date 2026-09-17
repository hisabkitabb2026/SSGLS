<?php

namespace App\Providers;

use App\Services\EventBus\Contracts\EventBusContract;
use App\Services\EventBus\DeadLetterQueue\DLQHandler;
use App\Services\EventBus\Handlers\EventHandler;
use App\Services\EventBus\Handlers\ExpenseApprovalHandler;
use App\Services\EventBus\Handlers\InvoiceCreatedHandler;
use App\Services\EventBus\Handlers\PaymentReceivedHandler;
use App\Services\EventBus\RabbitMQEventBus;
use App\Services\EventBus\Retry\RetryPolicy;
use App\Services\EventBus\Tracing\TracingService;
use Illuminate\Support\ServiceProvider;

/**
 * Event Bus Service Provider
 *
 * Registers event bus and related services
 */
class EventBusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register configuration
        $this->mergeConfigFrom(
            base_path('config/event-bus.php'),
            'event-bus'
        );

        // Register Tracing Service
        $this->app->singleton(TracingService::class, function () {
            return new TracingService(config('event-bus.tracing', []));
        });

        // Register Retry Policy
        $this->app->singleton(RetryPolicy::class, function () {
            return new RetryPolicy(config('event-bus.retry', []));
        });

        // Register Event Bus
        $this->app->singleton(EventBusContract::class, function () {
            return new RabbitMQEventBus(
                config('event-bus'),
                $this->app->make(TracingService::class),
            );
        });

        // Register alias for convenience
        $this->app->alias(EventBusContract::class, 'event-bus');
        $this->app->alias(EventBusContract::class, RabbitMQEventBus::class);

        // Register DLQ Handler
        $this->app->singleton(DLQHandler::class, function () {
            return new DLQHandler(config('event-bus'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            base_path('config/event-bus.php') => config_path('event-bus.php'),
        ], 'event-bus-config');

        // Register event handlers
        $this->registerEventHandlers();

        // Schedule cleanup commands
        $this->scheduleCleanup();
    }

    /**
     * Register event handlers
     */
    protected function registerEventHandlers(): void
    {
        $handlers = [
            InvoiceCreatedHandler::class,
            ExpenseApprovalHandler::class,
            PaymentReceivedHandler::class,
        ];

        foreach ($handlers as $handler) {
            $this->app->make($handler);

            // Subscribe handler to patterns
            if (method_exists($handler, 'subscribe')) {
                $patterns = $handler::subscribe();
                $eventBus = $this->app->make(EventBusContract::class);

                foreach ($patterns as $pattern) {
                    $eventBus->subscribe($pattern, function ($event) use ($handler) {
                        $instance = $this->app->make($handler);
                        if ($instance instanceof EventHandler) {
                            $instance->handle($event);
                        }
                    });
                }
            }
        }
    }

    /**
     * Schedule cleanup tasks
     */
    protected function scheduleCleanup(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // This would be called from a scheduled task
        // Registered in app/Console/Kernel.php schedule() method
    }
}
