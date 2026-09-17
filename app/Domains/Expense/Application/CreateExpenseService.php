<?php

namespace App\Domains\Expense\Application;

use App\Domains\Expense\Contracts\ExpenseRepository;
use App\Domains\Expense\Data\CreateExpenseData;
use App\Domains\Expense\Events\ExpenseCreated;
use App\Domains\Expense\Models\Expense;
use Illuminate\Support\Facades\Event;

class CreateExpenseService
{
    public function __construct(
        private ExpenseRepository $repository,
    ) {}

    public function execute(CreateExpenseData $data): Expense
    {
        $expense = $this->repository->create($data->toArray());

        Event::dispatch(new ExpenseCreated($expense));

        return $expense;
    }
}
