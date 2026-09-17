<?php

namespace App\Policies;

use App\Models\LorryReceipt;

class LorryReceiptPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'lorry-receipt';

    protected string $modelClass = LorryReceipt::class;
}
