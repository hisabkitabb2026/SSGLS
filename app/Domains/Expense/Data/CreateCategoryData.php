<?php

namespace App\Domains\Expense\Data;

readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
        public string $color,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'company_id' => $this->company_id,
        ];
    }
}
