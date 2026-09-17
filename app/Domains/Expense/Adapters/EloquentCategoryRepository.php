<?php

namespace App\Domains\Expense\Adapters;

use App\Domains\Expense\Contracts\CategoryRepository;
use App\Domains\Expense\Models\ExpenseCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class EloquentCategoryRepository implements CategoryRepository
{
    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data);
    }

    public function update(int $id, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->update($data);

        return $category;
    }

    public function find(int $id): ExpenseCategory
    {
        return ExpenseCategory::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return ExpenseCategory::destroy($id) > 0;
    }

    public function all($company): Collection
    {
        return ExpenseCategory::where('company_id', $company instanceof Company ? $company->id : $company)->get();
    }
}
