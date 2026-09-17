<?php

namespace App\Domains\Settings\Policies;

use App\Domains\Settings\Models\TaxType;
use App\Models\User;

class TaxTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TaxType $taxType): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function update(User $user, TaxType $taxType): bool
    {
        return $user->hasRole('owner');
    }

    public function delete(User $user, TaxType $taxType): bool
    {
        return $user->hasRole('owner');
    }
}
