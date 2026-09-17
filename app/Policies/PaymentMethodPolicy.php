<?php

namespace App\Policies;

use App\Models\PaymentMethod;

class PaymentMethodPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'payment_method';

    protected string $modelClass = PaymentMethod::class;
}
