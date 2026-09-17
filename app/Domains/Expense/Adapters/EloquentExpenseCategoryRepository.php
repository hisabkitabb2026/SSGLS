<?php

namespace App\Domains\Expense\Adapters;

use App\Domains\Expense\Contracts\ExpenseCategoryRepository;
use App\Domains\Expense\Models\ExpenseCategory;

class EloquentExpenseCategoryRepository implements ExpenseCategoryRepository
{
    public function create(array $data)
    {
        return ExpenseCategory::create($data);
    }

    public function update(int $id, array $data)
    {
        $cat = ExpenseCategory::findOrFail($id);
        $cat->update($data);

        return $cat;
    }

    public function find(int $id)
    {
        return ExpenseCategory::findOrFail($id);
    }

    public function delete(int $id)
    {
        return ExpenseCategory::destroy($id);
    }

    public function all($company)
    {
        return ExpenseCategory::where('company_id', $company->id)->get();
    }
}
