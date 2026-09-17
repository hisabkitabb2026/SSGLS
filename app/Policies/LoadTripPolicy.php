<?php

namespace App\Policies;

use App\Models\LoadTrip;

/**
 * Policy for LoadTrip — extends BaseCompanyPolicy so that
 * access is controlled by Bouncer abilities (view-load-trip,
 * create-load-trip, edit-load-trip, delete-load-trip).
 *
 * Owners and super admins bypass ability checks via hasFullCompanyAccess().
 */
class LoadTripPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'load-trip';

    protected string $modelClass = LoadTrip::class;
}
