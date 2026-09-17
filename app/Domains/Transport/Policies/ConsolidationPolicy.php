<?php

namespace App\Domains\Transport\Policies;

use App\Domains\Transport\Models\ConsolidationGroup;
use App\Models\User;

class ConsolidationPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, ConsolidationGroup $consolidation): bool
    {
        return $user->company_id === $consolidation->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('create-consolidation') || $user->bouncer()->can('manage-transport');
    }

    public function update(User $user, ConsolidationGroup $consolidation): bool
    {
        return $user->company_id === $consolidation->company_id &&
               ($user->bouncer()->can('edit-consolidation') || $user->bouncer()->can('manage-transport'));
    }

    public function delete(User $user, ConsolidationGroup $consolidation): bool
    {
        return $user->company_id === $consolidation->company_id &&
               ($user->bouncer()->can('delete-consolidation') || $user->bouncer()->can('manage-transport'));
    }
}
