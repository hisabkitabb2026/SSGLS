<?php

namespace App\Policies;

use App\Models\TaxType;

class TaxTypePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'tax-type';

    protected string $modelClass = TaxType::class;
}
