<?php

use App\Domains\Invoicing\Http\Controllers\InvoiceController;
use App\Domains\Invoicing\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    // Invoice routes
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/publish', [InvoiceController::class, 'publish']);
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate']);

    // Payment routes
    Route::apiResource('payments', PaymentController::class);
});
