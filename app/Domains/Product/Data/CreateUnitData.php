<?php

namespace App\Domains\Product\Data;

readonly class CreateUnitData
{
    public function __construct(
        public string $name,
        public string $symbol,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'symbol' => $this->symbol,
            'company_id' => $this->company_id,
        ];
    }
}
