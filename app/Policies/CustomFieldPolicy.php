<?php

namespace App\Policies;

use App\Models\CustomField;

class CustomFieldPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'custom-field';

    protected string $modelClass = CustomField::class;
}
