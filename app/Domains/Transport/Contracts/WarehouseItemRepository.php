<?php

namespace App\Domains\Transport\Contracts;

use App\Domains\Transport\Models\WarehouseItem;

interface WarehouseItemRepository
{
    public function create(array $data): WarehouseItem;

    public function find(int $id): ?WarehouseItem;

    public function update(int $id, array $data): WarehouseItem;

    public function delete(int $id): bool;

    public function all($company);

    public function getByStatus(string $status, $company);

    public function getByDestination(string $destination, $company);
}
