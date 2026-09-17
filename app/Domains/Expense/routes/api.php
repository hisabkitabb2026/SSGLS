<?php

use App\Domains\Expense\Http\Controllers\CategoryController;
use App\Domains\Expense\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->prefix('api/v1')->group(function () {
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('expense-categories', CategoryController::class);
});
