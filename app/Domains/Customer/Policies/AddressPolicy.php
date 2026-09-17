<?php

namespace App\Domains\Customer\Policies;

use App\Domains\Customer\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return $user->company_id === $address->customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('create-address') || $user->bouncer()->can('manage-customers');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->company_id === $address->customer->company_id &&
               ($user->bouncer()->can('edit-address') || $user->bouncer()->can('manage-customers'));
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->company_id === $address->customer->company_id &&
               ($user->bouncer()->can('delete-address') || $user->bouncer()->can('manage-customers'));
    }
}
