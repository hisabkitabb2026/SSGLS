<?php

namespace App\Domains\Customer\Data;

readonly class UpdateCustomerData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $customer_type,
        public bool $active,
    ) {}
}
