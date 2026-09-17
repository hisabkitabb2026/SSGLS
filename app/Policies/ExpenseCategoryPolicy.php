<?php

namespace App\Policies;

use App\Models\ExpenseCategory;

class ExpenseCategoryPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'expense_category';

    protected string $modelClass = ExpenseCategory::class;
}
