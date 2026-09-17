<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Silber\Bouncer\BouncerFacade;

class InvoicePolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'invoice';

    protected string $modelClass = Invoice::class;

    /**
     * Override update to check allow_edit (retrospective edit settings).
     *
     * Must use Model $model (not Invoice $invoice) to match the parent's
     * BaseCompanyPolicy::update(User, Model) signature — PHP 8.4 enforces
     * contravariant parameter compatibility on overrides.
     */
    public function update(User $user, Model $model): bool
    {
        if (BouncerFacade::can('edit-invoice', $model) && $user->hasCompany($model->company_id)) {
            /** @var Invoice $model */
            return $model->allow_edit;
        }

        return false;
    }
}
