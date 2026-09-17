<?php

namespace App\Policies;

use App\Models\Unit;

class UnitPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'unit';

    protected string $modelClass = Unit::class;
}
