<?php

namespace App\Domains\Transport\Application;

use App\Domains\Transport\Contracts\WarehouseItemRepository;
use App\Domains\Transport\Models\WarehouseItem;

class UpdateWarehouseItemService
{
    public function __construct(
        private WarehouseItemRepository $repository,
    ) {}

    public function execute(int $id, array $data): WarehouseItem
    {
        return $this->repository->update($id, $data);
    }
}
