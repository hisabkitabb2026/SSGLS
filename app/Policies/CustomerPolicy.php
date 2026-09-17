<?php

namespace App\Policies;

use App\Models\Customer;

class CustomerPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'customer';

    protected string $modelClass = Customer::class;
}
