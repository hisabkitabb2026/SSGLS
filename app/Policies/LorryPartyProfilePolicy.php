<?php

namespace App\Policies;

use App\Models\LorryPartyProfile;

class LorryPartyProfilePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'lorry_party_profile';

    protected string $modelClass = LorryPartyProfile::class;
}
