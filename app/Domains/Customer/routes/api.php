<?php

use App\Domains\Customer\Http\Controllers\AddressController;
use App\Domains\Customer\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    // Customer routes
    Route::apiResource('customers', CustomerController::class);

    // Address routes
    Route::post('addresses', [AddressController::class, 'store']);
    Route::get('customers/{customerId}/addresses', [AddressController::class, 'indexByCustomer']);
    Route::get('addresses/{address}', [AddressController::class, 'show']);
    Route::put('addresses/{address}', [AddressController::class, 'update']);
    Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
});
