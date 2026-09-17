<?php

use App\Domains\Transport\Http\Controllers\ConsolidationController;
use App\Domains\Transport\Http\Controllers\LoadTripController;
use App\Domains\Transport\Http\Controllers\LorryReceiptController;
use App\Domains\Transport\Http\Controllers\WarehouseItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    // Lorry Receipt routes
    Route::apiResource('lorry-receipts', LorryReceiptController::class);
    Route::get('lorry-receipts/status/{status}', [LorryReceiptController::class, 'byStatus']);

    // Warehouse Item routes
    Route::apiResource('warehouse-items', WarehouseItemController::class);
    Route::get('warehouse-items/dashboard', [WarehouseItemController::class, 'dashboard']);
    Route::patch('warehouse-items/{id}/status', [WarehouseItemController::class, 'updateStatus']);
    Route::get('warehouse-items/destination/{destination}', [WarehouseItemController::class, 'byDestination']);

    // Consolidation routes
    Route::apiResource('consolidations', ConsolidationController::class);

    // Load Trip routes
    Route::apiResource('load-trips', LoadTripController::class);
});
