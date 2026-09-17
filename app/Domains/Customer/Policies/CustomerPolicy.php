<?php

namespace App\Domains\Customer\Policies;

use App\Domains\Customer\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('create-customer') || $user->bouncer()->can('manage-customers');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id &&
               ($user->bouncer()->can('edit-customer') || $user->bouncer()->can('manage-customers'));
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->company_id === $customer->company_id &&
               ($user->bouncer()->can('delete-customer') || $user->bouncer()->can('manage-customers'));
    }
}
