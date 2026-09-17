<?php

namespace App\Domains\Settings\Data;

readonly class CreatePaymentMethodData
{
    public function __construct(
        public string $name,
        public string $type,
        public int $company_id,
    ) {}

    public function toArray(): array
    {
        return ['name' => $this->name, 'type' => $this->type, 'company_id' => $this->company_id];
    }
}
