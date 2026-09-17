<?php

namespace App\Domains\Expense\Adapters;

use App\Domains\Expense\Contracts\ExpenseRepository;
use App\Domains\Expense\Models\Expense;
use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class EloquentExpenseRepository implements ExpenseRepository
{
    public function create(array $data): Expense
    {
        return Expense::create($data);
    }

    public function update(int $id, array $data): Expense
    {
        $expense = Expense::findOrFail($id);
        $expense->update($data);

        return $expense;
    }

    public function find(int $id): Expense
    {
        return Expense::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return Expense::destroy($id) > 0;
    }

    public function all($company): Collection
    {
        return Expense::where('company_id', $company instanceof Company ? $company->id : $company)->get();
    }
}
