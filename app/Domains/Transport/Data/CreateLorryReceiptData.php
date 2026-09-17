<?php

namespace App\Domains\Transport\Data;

readonly class CreateLorryReceiptData
{
    public function __construct(
        public string $vehicle_number,
        public string $owner_name,
        public string $driver_name,
        public string $from_location,
        public string $to_location,
        public float $freight_amount,
        public int $company_id,
    ) {}
}
