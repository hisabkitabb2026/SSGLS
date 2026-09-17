<?php

namespace App\Domains\Product;

use App\Domains\Product\Adapters\EloquentItemRepository;
use App\Domains\Product\Adapters\EloquentUnitRepository;
use App\Domains\Product\Application\CreateItemService;
use App\Domains\Product\Application\CreateUnitService;
use App\Domains\Product\Contracts\ItemRepository;
use App\Domains\Product\Contracts\UnitRepository;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ItemRepository::class, EloquentItemRepository::class);
        $this->app->bind(UnitRepository::class, EloquentUnitRepository::class);

        $this->app->singleton(CreateItemService::class);
        $this->app->singleton(CreateUnitService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
