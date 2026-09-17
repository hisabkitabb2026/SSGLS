<?php

namespace App\Domains\Product\Adapters;

use App\Domains\Product\Contracts\ItemRepository;
use App\Domains\Product\Models\Item;
use Illuminate\Database\Eloquent\Collection;

class EloquentItemRepository implements ItemRepository
{
    public function create(array $data): Item
    {
        return Item::create($data);
    }

    public function update(int $id, array $data): Item
    {
        $item = Item::findOrFail($id);
        $item->update($data);

        return $item;
    }

    public function find(int $id): Item
    {
        return Item::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return Item::destroy($id) > 0;
    }

    public function all(): Collection
    {
        return Item::all();
    }
}
