<?php

namespace App\Domains\Expense\Http\Controllers;

use App\Domains\Expense\Application\CreateExpenseService;
use App\Domains\Expense\Contracts\ExpenseRepository;
use App\Domains\Expense\Data\CreateExpenseData;
use App\Domains\Expense\Http\Requests\StoreExpenseRequest;
use App\Domains\Expense\Http\Resources\ExpenseResource;
use App\Domains\Expense\Models\Expense;
use App\Http\Controllers\Controller;
use App\Services\Cache\DashboardCacheService;

class ExpenseController extends Controller
{
    public function __construct(
        private ExpenseRepository $repository,
        private CreateExpenseService $service,
        private DashboardCacheService $cacheService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Expense::class);
        $expenses = $this->repository->all(auth()->user()->company_id);

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request)
    {
        $this->authorize('create', Expense::class);
        $expense = $this->service->execute(
            new CreateExpenseData(
                description: $request->validated('description'),
                amount: $request->validated('amount'),
                date: $request->validated('date'),
                category_id: $request->validated('category_id'),
                company_id: auth()->user()->company_id,
            )
        );

        // Invalidate dashboard cache after expense creation
        $this->cacheService->invalidate(auth()->user()->company_id);

        return new ExpenseResource($expense);
    }

    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense);
    }

    public function update(StoreExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $data = $request->validated();

        // Set base amount when updating
        if (isset($data['amount'])) {
            $data['base_amount'] = $data['amount'];
        }

        $updated = $this->repository->update($expense->id, $data);

        // Invalidate dashboard cache after expense update
        $this->cacheService->invalidate($expense->company_id);

        return new ExpenseResource($updated);
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);
        $companyId = $expense->company_id;
        $this->repository->delete($expense->id);

        // Invalidate dashboard cache after expense deletion
        $this->cacheService->invalidate($companyId);

        return response()->noContent();
    }
}
