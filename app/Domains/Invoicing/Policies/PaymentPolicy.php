<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->company_id === $payment->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('record-payment') || $user->bouncer()->can('manage-invoices');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->company_id === $payment->company_id &&
               ($user->bouncer()->can('delete-payment') || $user->bouncer()->can('manage-invoices'));
    }
}
