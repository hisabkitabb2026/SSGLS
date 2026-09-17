<?php

namespace App\Policies;

use App\Models\TruckServiceSchedule;

class TruckServiceSchedulePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'truck-service-schedule';

    protected string $modelClass = TruckServiceSchedule::class;
}
