<?php

namespace App\Domains\Product\Adapters;

use App\Domains\Product\Contracts\UnitRepository;
use App\Domains\Product\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

class EloquentUnitRepository implements UnitRepository
{
    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    public function update(int $id, array $data): Unit
    {
        $unit = Unit::findOrFail($id);
        $unit->update($data);

        return $unit;
    }

    public function find(int $id): Unit
    {
        return Unit::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return Unit::destroy($id) > 0;
    }

    public function all(): Collection
    {
        return Unit::all();
    }
}
