<?php

namespace App\Domains\Expense\Contracts;

use App\Domains\Expense\Models\Expense;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseRepository
{
    public function create(array $data): Expense;

    public function update(int $id, array $data): Expense;

    public function find(int $id): Expense;

    public function delete(int $id): bool;

    public function all($company): Collection;
}
