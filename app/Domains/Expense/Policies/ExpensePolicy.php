<?php

namespace App\Domains\Expense\Policies;

use App\Domains\Expense\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->company_id === $expense->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->company_id === $expense->company_id;
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->company_id === $expense->company_id;
    }
}
