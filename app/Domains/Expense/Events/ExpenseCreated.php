<?php

namespace App\Domains\Expense\Events;

use App\Domains\Expense\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExpenseCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Expense $expense) {}
}
