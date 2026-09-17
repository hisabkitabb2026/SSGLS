<?php

namespace App\Domains\Transport\Policies;

use App\Domains\Transport\Models\WarehouseItem;
use App\Models\User;

class WarehouseItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, WarehouseItem $item): bool
    {
        return $user->company_id === $item->company_id;
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function update(User $user, WarehouseItem $item): bool
    {
        return $user->company_id === $item->company_id;
    }

    public function delete(User $user, WarehouseItem $item): bool
    {
        return $user->company_id === $item->company_id;
    }
}
