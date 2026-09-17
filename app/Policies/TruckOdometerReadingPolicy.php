<?php

namespace App\Policies;

use App\Models\TruckOdometerReading;

class TruckOdometerReadingPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'odometer-reading';

    protected string $modelClass = TruckOdometerReading::class;
}
