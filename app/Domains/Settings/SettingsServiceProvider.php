<?php

namespace App\Domains\Settings;

use App\Domains\Settings\Adapters\EloquentCurrencyRepository;
use App\Domains\Settings\Adapters\EloquentPaymentMethodRepository;
use App\Domains\Settings\Adapters\EloquentTaxRepository;
use App\Domains\Settings\Adapters\EloquentTaxTypeRepository;
use App\Domains\Settings\Contracts\CurrencyRepository;
use App\Domains\Settings\Contracts\PaymentMethodRepository;
use App\Domains\Settings\Contracts\TaxRepository;
use App\Domains\Settings\Contracts\TaxTypeRepository;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxRepository::class, EloquentTaxRepository::class);
        $this->app->bind(CurrencyRepository::class, EloquentCurrencyRepository::class);
        $this->app->bind(PaymentMethodRepository::class, EloquentPaymentMethodRepository::class);
        $this->app->bind(TaxTypeRepository::class, EloquentTaxTypeRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
