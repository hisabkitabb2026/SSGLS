<?php

namespace App\Domains\Settings\Data;

readonly class CreateTaxData
{
    public function __construct(
        public string $name,
        public float $rate,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return ['name' => $this->name, 'rate' => $this->rate, 'company_id' => $this->company_id];
    }
}
