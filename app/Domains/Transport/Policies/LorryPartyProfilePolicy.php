<?php

namespace App\Domains\Transport\Policies;

use App\Domains\Transport\Models\LorryPartyProfile;
use App\Models\User;

class LorryPartyProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, LorryPartyProfile $profile): bool
    {
        return $user->company_id === $profile->company_id;
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function update(User $user, LorryPartyProfile $profile): bool
    {
        return $user->company_id === $profile->company_id;
    }

    public function delete(User $user, LorryPartyProfile $profile): bool
    {
        return $user->company_id === $profile->company_id;
    }
}
