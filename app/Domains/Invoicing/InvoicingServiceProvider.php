<?php

namespace App\Domains\Invoicing;

use App\Domains\Invoicing\Adapters\EloquentInvoiceRepository;
use App\Domains\Invoicing\Adapters\EloquentPaymentRepository;
use App\Domains\Invoicing\Adapters\EloquentRecurringInvoiceRepository;
use App\Domains\Invoicing\Application\CreateInvoiceService;
use App\Domains\Invoicing\Application\DuplicateInvoiceService;
use App\Domains\Invoicing\Application\PublishInvoiceService;
use App\Domains\Invoicing\Application\RecordPaymentService;
use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Contracts\PaymentRepository;
use App\Domains\Invoicing\Contracts\RecurringInvoiceRepository;
use Illuminate\Support\ServiceProvider;

class InvoicingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository contracts to implementations
        $this->app->bind(
            InvoiceRepository::class,
            EloquentInvoiceRepository::class
        );

        $this->app->bind(
            PaymentRepository::class,
            EloquentPaymentRepository::class
        );

        $this->app->bind(
            RecurringInvoiceRepository::class,
            EloquentRecurringInvoiceRepository::class
        );

        // Register application services
        $this->app->singleton(CreateInvoiceService::class);
        $this->app->singleton(PublishInvoiceService::class);
        $this->app->singleton(RecordPaymentService::class);
        $this->app->singleton(DuplicateInvoiceService::class);
    }

    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Register policies
        $this->registerPolicies();

        // Register event listeners
        $this->registerEventListeners();
    }

    protected function registerPolicies(): void
    {
        // Policies will be registered via Laravel's policy auto-discovery
    }

    protected function registerEventListeners(): void
    {
        // Event listeners registered in boot method
    }
}
