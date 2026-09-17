<?php

namespace App\Policies;

use App\Models\Expense;

class ExpensePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'expense';

    protected string $modelClass = Expense::class;
}
