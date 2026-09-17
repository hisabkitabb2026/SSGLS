<?php

namespace App\Domains\Product\Contracts;

use App\Domains\Product\Models\Item;
use Illuminate\Database\Eloquent\Collection;

interface ItemRepository
{
    public function create(array $data): Item;

    public function update(int $id, array $data): Item;

    public function find(int $id): Item;

    public function delete(int $id): bool;

    public function all(): Collection;
}
