<?php

namespace App\Domains\Expense\Data;

readonly class CreateExpenseData
{
    public function __construct(
        public string $description,
        public float $amount,
        public string $date,
        public int $category_id,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date,
            'category_id' => $this->category_id,
            'company_id' => $this->company_id,
            'base_amount' => $this->amount,
        ];
    }
}
