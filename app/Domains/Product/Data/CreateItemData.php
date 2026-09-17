<?php

namespace App\Domains\Product\Data;

readonly class CreateItemData
{
    public function __construct(
        public string $name,
        public string $code,
        public int $unit_id,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => $this->unit_id,
            'company_id' => $this->company_id,
        ];
    }
}
