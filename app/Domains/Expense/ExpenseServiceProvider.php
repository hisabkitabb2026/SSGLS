<?php

namespace App\Domains\Expense;

use App\Domains\Expense\Adapters\EloquentCategoryRepository;
use App\Domains\Expense\Adapters\EloquentExpenseRepository;
use App\Domains\Expense\Application\CreateCategoryService;
use App\Domains\Expense\Application\CreateExpenseService;
use App\Domains\Expense\Contracts\CategoryRepository;
use App\Domains\Expense\Contracts\ExpenseRepository;
use Illuminate\Support\ServiceProvider;

class ExpenseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExpenseRepository::class, EloquentExpenseRepository::class);
        $this->app->bind(CategoryRepository::class, EloquentCategoryRepository::class);

        $this->app->singleton(CreateExpenseService::class);
        $this->app->singleton(CreateCategoryService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }
}
