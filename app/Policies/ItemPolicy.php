<?php

namespace App\Policies;

use App\Models\Item;

class ItemPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'item';

    protected string $modelClass = Item::class;
}
