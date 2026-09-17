<?php

use App\Domains\Settings\Http\Controllers\CurrencyController;
use App\Domains\Settings\Http\Controllers\PaymentMethodController;
use App\Domains\Settings\Http\Controllers\TaxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    Route::apiResource('taxes', TaxController::class);
    Route::apiResource('currencies', CurrencyController::class);
    Route::apiResource('payment-methods', PaymentMethodController::class);
});
