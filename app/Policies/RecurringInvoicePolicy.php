<?php

namespace App\Policies;

use App\Models\RecurringInvoice;

class RecurringInvoicePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'recurring-invoice';

    protected string $modelClass = RecurringInvoice::class;
}
