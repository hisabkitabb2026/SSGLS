<?php

namespace App\Domains\Customer\Data;

readonly class CreateAddressData
{
    public function __construct(
        public int $customer_id,
        public string $street_address,
        public string $city,
        public string $state,
        public string $postal_code,
        public string $country,
        public bool $is_primary,
    ) {}
}
