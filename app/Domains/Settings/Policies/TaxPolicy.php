<?php

namespace App\Domains\Settings\Policies;

use App\Domains\Settings\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->company_id === $tax->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->company_id === $tax->company_id;
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->company_id === $tax->company_id;
    }
}
