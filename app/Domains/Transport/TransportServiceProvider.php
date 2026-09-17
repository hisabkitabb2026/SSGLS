<?php

namespace App\Domains\Transport;

use App\Domains\Transport\Adapters\EloquentConsolidationRepository;
use App\Domains\Transport\Adapters\EloquentLoadTripRepository;
use App\Domains\Transport\Adapters\EloquentLorryReceiptRepository;
use App\Domains\Transport\Adapters\EloquentPartyProfileRepository;
use App\Domains\Transport\Adapters\EloquentWarehouseItemRepository;
use App\Domains\Transport\Application\ConsolidateItemsService;
use App\Domains\Transport\Application\CreateLoadTripService;
use App\Domains\Transport\Application\CreateLorryReceiptService;
use App\Domains\Transport\Application\UpdateWarehouseItemService;
use App\Domains\Transport\Contracts\ConsolidationRepository;
use App\Domains\Transport\Contracts\LoadTripRepository;
use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Contracts\PartyProfileRepository;
use App\Domains\Transport\Contracts\WarehouseItemRepository;
use Illuminate\Support\ServiceProvider;

class TransportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository contracts to implementations
        $this->app->bind(
            LorryReceiptRepository::class,
            EloquentLorryReceiptRepository::class
        );

        $this->app->bind(
            WarehouseItemRepository::class,
            EloquentWarehouseItemRepository::class
        );

        $this->app->bind(
            PartyProfileRepository::class,
            EloquentPartyProfileRepository::class
        );

        $this->app->bind(
            ConsolidationRepository::class,
            EloquentConsolidationRepository::class
        );

        $this->app->bind(
            LoadTripRepository::class,
            EloquentLoadTripRepository::class
        );

        // Register application services
        $this->app->singleton(CreateLorryReceiptService::class);
        $this->app->singleton(UpdateWarehouseItemService::class);
        $this->app->singleton(ConsolidateItemsService::class);
        $this->app->singleton(CreateLoadTripService::class);
    }

    public function boot(): void
    {
        // NOTE: Domain routes are DISABLED to prevent duplicate route registration.
        // The active routes live in routes/api.php under the auth:sanctum + company
        // + bouncer middleware group. The Domain routes file duplicates these
        // endpoints without the proper middleware stack, causing route conflicts.
        // To re-enable, uncomment the line below — but ensure routes/api.php
        // does not also register the same endpoints.
        // $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Load migrations
        $this->loadMigrationsFrom(database_path('migrations'));

        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/transport.php' => config_path('transport.php'),
        ], 'config');

        // Register policies
        $this->registerPolicies();

        // Register event listeners
        $this->registerEventListeners();
    }

    protected function registerPolicies(): void
    {
        // Policies will be registered via Laravel's policy auto-discovery
        // in app/Policies/ directory
    }

    protected function registerEventListeners(): void
    {
        // Event listeners registered in boot method or via listeners in the domain
    }
}
