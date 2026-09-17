<?php

namespace App\Domains\Transport\Policies;

use App\Domains\Transport\Models\LoadTrip;
use App\Models\User;

class LoadTripPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, LoadTrip $trip): bool
    {
        return $user->company_id === $trip->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('create-load-trip') || $user->bouncer()->can('manage-transport');
    }

    public function update(User $user, LoadTrip $trip): bool
    {
        return $user->company_id === $trip->company_id &&
               ($user->bouncer()->can('edit-load-trip') || $user->bouncer()->can('manage-transport'));
    }

    public function delete(User $user, LoadTrip $trip): bool
    {
        return $user->company_id === $trip->company_id &&
               ($user->bouncer()->can('delete-load-trip') || $user->bouncer()->can('manage-transport'));
    }
}
