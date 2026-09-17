<?php

namespace App\Domains\Customer;

use App\Domains\Customer\Adapters\EloquentAddressRepository;
use App\Domains\Customer\Adapters\EloquentCustomerRepository;
use App\Domains\Customer\Application\CreateAddressService;
use App\Domains\Customer\Application\CreateCustomerService;
use App\Domains\Customer\Contracts\AddressRepository;
use App\Domains\Customer\Contracts\CustomerRepository;
use Illuminate\Support\ServiceProvider;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
        $this->app->bind(AddressRepository::class, EloquentAddressRepository::class);

        $this->app->singleton(CreateCustomerService::class);
        $this->app->singleton(CreateAddressService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
