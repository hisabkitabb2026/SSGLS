<?php

namespace App\Policies;

use App\Models\TruckMaintenance;

class TruckMaintenancePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'truck-maintenance';

    protected string $modelClass = TruckMaintenance::class;
}
