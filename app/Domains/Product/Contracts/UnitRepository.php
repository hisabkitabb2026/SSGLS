<?php

namespace App\Domains\Product\Contracts;

use App\Domains\Product\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

interface UnitRepository
{
    public function create(array $data): Unit;

    public function update(int $id, array $data): Unit;

    public function find(int $id): Unit;

    public function delete(int $id): bool;

    public function all(): Collection;
}
