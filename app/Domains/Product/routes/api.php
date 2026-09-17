<?php

use App\Domains\Product\Http\Controllers\ItemController;
use App\Domains\Product\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    Route::apiResource('items', ItemController::class);
    Route::apiResource('units', UnitController::class);
});
