<?php

namespace App\Policies;

use App\Models\Truck;

class TruckPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'truck';

    protected string $modelClass = Truck::class;
}
