<?php

namespace App\Domains\Expense\Application;

use App\Domains\Expense\Contracts\CategoryRepository;
use App\Domains\Expense\Data\CreateCategoryData;
use App\Domains\Expense\Models\ExpenseCategory;

class CreateCategoryService
{
    public function __construct(
        private CategoryRepository $repository,
    ) {}

    public function execute(CreateCategoryData $data): ExpenseCategory
    {
        return $this->repository->create($data->toArray());
    }
}
