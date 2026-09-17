<?php

namespace App\Policies;

use App\Models\Payment;

class PaymentPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'payment';

    protected string $modelClass = Payment::class;
}
