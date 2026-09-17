<?php

namespace App\Domains\Product\Policies;

use App\Domains\Product\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Item $item): bool
    {
        return $user->company_id === $item->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Item $item): bool
    {
        return $user->company_id === $item->company_id;
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->company_id === $item->company_id;
    }
}
