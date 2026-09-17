<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return $user->bouncer()->can('create-invoice') || $user->bouncer()->can('manage-invoices');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id &&
               ($user->bouncer()->can('edit-invoice') || $user->bouncer()->can('manage-invoices'));
    }

    public function publish(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id &&
               $invoice->status === 'draft' &&
               ($user->bouncer()->can('publish-invoice') || $user->bouncer()->can('manage-invoices'));
    }

    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id &&
               ($user->bouncer()->can('duplicate-invoice') || $user->bouncer()->can('manage-invoices'));
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id &&
               ($user->bouncer()->can('delete-invoice') || $user->bouncer()->can('manage-invoices'));
    }
}
