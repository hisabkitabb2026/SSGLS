<?php

use App\Domains\Customer\CustomerServiceProvider;
use App\Domains\Expense\ExpenseServiceProvider;
use App\Domains\Invoicing\InvoicingServiceProvider;
use App\Domains\Product\ProductServiceProvider;
use App\Domains\Settings\SettingsServiceProvider;
use App\Domains\Transport\TransportServiceProvider;
use App\Providers\AiServiceProvider;
use App\Providers\AppConfigProvider;
use App\Providers\AppServiceProvider;
use App\Providers\DriverRegistryProvider;
use App\Providers\DropboxServiceProvider;
use App\Providers\PdfServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\ScrambleServiceProvider;
use App\Providers\ViewServiceProvider;
use App\Support\Hashids\HashidsServiceProvider;

return [
    HashidsServiceProvider::class,
    AppServiceProvider::class,
    TransportServiceProvider::class,
    InvoicingServiceProvider::class,
    CustomerServiceProvider::class,
    ProductServiceProvider::class,
    ExpenseServiceProvider::class,
    SettingsServiceProvider::class,
    RouteServiceProvider::class,
    DropboxServiceProvider::class,
    ViewServiceProvider::class,
    PdfServiceProvider::class,
    DriverRegistryProvider::class,
    AiServiceProvider::class,
    AppConfigProvider::class,
    ScrambleServiceProvider::class,
];
