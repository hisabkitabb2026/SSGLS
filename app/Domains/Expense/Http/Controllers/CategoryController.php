<?php

namespace App\Domains\Expense\Http\Controllers;

use App\Domains\Expense\Application\CreateCategoryService;
use App\Domains\Expense\Contracts\CategoryRepository;
use App\Domains\Expense\Data\CreateCategoryData;
use App\Domains\Expense\Http\Requests\StoreCategoryRequest;
use App\Domains\Expense\Http\Resources\CategoryResource;
use App\Domains\Expense\Models\ExpenseCategory;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryRepository $repository,
        private CreateCategoryService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', ExpenseCategory::class);
        $categories = $this->repository->all(auth()->user()->company_id);

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->authorize('create', ExpenseCategory::class);
        $category = $this->service->execute(
            new CreateCategoryData(
                name: $request->validated('name'),
                color: $request->validated('color', '#000000'),
                company_id: auth()->user()->company_id,
            )
        );

        return new CategoryResource($category);
    }

    public function show(ExpenseCategory $category)
    {
        $this->authorize('view', $category);

        return new CategoryResource($category);
    }

    public function update(StoreCategoryRequest $request, ExpenseCategory $category)
    {
        $this->authorize('update', $category);
        $updated = $this->repository->update($category->id, $request->validated());

        return new CategoryResource($updated);
    }

    public function destroy(ExpenseCategory $category)
    {
        $this->authorize('delete', $category);
        $this->repository->delete($category->id);

        return response()->noContent();
    }
}
