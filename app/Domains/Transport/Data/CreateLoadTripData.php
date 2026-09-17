<?php

namespace App\Domains\Transport\Data;

readonly class CreateLoadTripData
{
    public function __construct(
        public string $name,
        public int $consolidation_id,
        public string $status,
        public ?string $notes,
        public int $company_id,
    ) {}
}
