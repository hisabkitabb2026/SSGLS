<?php

namespace App\Domains\Expense\Contracts;

use App\Domains\Expense\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepository
{
    public function create(array $data): ExpenseCategory;

    public function update(int $id, array $data): ExpenseCategory;

    public function find(int $id): ExpenseCategory;

    public function delete(int $id): bool;

    public function all($company): Collection;
}
