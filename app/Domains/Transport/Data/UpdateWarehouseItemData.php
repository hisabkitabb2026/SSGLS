<?php

namespace App\Domains\Transport\Data;

readonly class UpdateWarehouseItemData
{
    public function __construct(
        public ?string $status = null,
        public ?string $destination = null,
        public ?int $quantity = null,
        public ?float $weight = null,
    ) {}
}
