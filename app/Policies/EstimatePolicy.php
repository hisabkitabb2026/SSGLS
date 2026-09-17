<?php

namespace App\Policies;

use App\Models\Estimate;

class EstimatePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'estimate';

    protected string $modelClass = Estimate::class;
}
