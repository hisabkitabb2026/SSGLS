<?php

namespace App\Domains\Customer\Data;

readonly class CreateCustomerData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $customer_type,
        public bool $active,
        public int $company_id,
    ) {}
}
