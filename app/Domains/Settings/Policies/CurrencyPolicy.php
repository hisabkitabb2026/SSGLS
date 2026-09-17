<?php

namespace App\Domains\Settings\Policies;

use App\Domains\Settings\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Currency $currency): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('owner');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasRole('owner');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasRole('owner');
    }
}
