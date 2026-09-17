<?php

namespace App\Domains\Transport\Repositories;

use App\Domains\Transport\Contracts\WarehouseItemRepository;
use App\Domains\Transport\Models\WarehouseItem;

class EloquentWarehouseItemRepository implements WarehouseItemRepository
{
    public function create(array $data): WarehouseItem
    {
        return WarehouseItem::create($data);
    }

    public function find(int $id): ?WarehouseItem
    {
        return WarehouseItem::find($id);
    }

    public function update(int $id, array $data): WarehouseItem
    {
        $item = WarehouseItem::findOrFail($id);
        $item->update($data);

        return $item;
    }

    public function delete(int $id): bool
    {
        return (bool) WarehouseItem::destroy($id);
    }

    public function all($company)
    {
        return WarehouseItem::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return WarehouseItem::where('company_id', $company->id)
            ->where('status', $status)->get();
    }

    public function getByDestination(string $destination, $company)
    {
        return WarehouseItem::where('company_id', $company->id)
            ->where('destination', $destination)->get();
    }
}
