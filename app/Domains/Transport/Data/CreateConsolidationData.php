<?php

namespace App\Domains\Transport\Data;

readonly class CreateConsolidationData
{
    public function __construct(
        public string $name,
        public string $destination,
        public string $status,
        public int $company_id,
        public array $warehouse_items = [],
    ) {}
}
