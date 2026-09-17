<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Silber\Bouncer\BouncerFacade;

/**
 * Base policy for all company-scoped models.
 *
 * Every company-scoped model (Invoice, Estimate, Payment, Expense, etc.)
 * shares the same authorization pattern:
 *   - viewAny:  can "view-{model}" on the class
 *   - view:     can "view-{model}" on the instance AND user belongs to the same company
 *   - create:   can "create-{model}" on the class
 *   - update:   can "edit-{model}" on the instance AND same company
 *   - delete:   can "delete-{model}" on the instance AND same company
 *   - restore:  same as delete
 *   - forceDelete: same as delete
 *   - send:     can "send-{model}" on the instance AND same company
 *   - deleteMultiple: can "delete-{model}" on the class
 *
 * Concrete policies only need to set:
 *   - protected string $abilityPrefix  (e.g. 'invoice', 'estimate', 'payment')
 *   - protected string $modelClass     (e.g. Invoice::class)
 *
 * If a policy needs custom behavior for a method (e.g. InvoicePolicy::update
 * checks $invoice->allow_edit), it can simply override that one method.
 */
abstract class BaseCompanyPolicy
{
    use HandlesAuthorization;

    /**
     * The ability prefix used in Bouncer (e.g. 'invoice' → 'view-invoice').
     */
    protected string $abilityPrefix;

    /**
     * The model class this policy protects.
     */
    protected string $modelClass;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($this->hasFullCompanyAccess($user)) {
            return true;
        }

        return BouncerFacade::can("view-{$this->abilityPrefix}", $this->modelClass);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("view-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($this->hasFullCompanyAccess($user)) {
            return true;
        }

        return BouncerFacade::can("create-{$this->abilityPrefix}", $this->modelClass);
    }

    /**
     * Determine whether the user can update the model.
     *
     * Override in child policies that need extra checks (e.g. allow_edit).
     */
    public function update(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("edit-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("delete-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("delete-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("delete-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can send the model via email.
     */
    public function send(User $user, Model $model): bool
    {
        if ($this->hasFullCompanyAccess($user, $model)) {
            return true;
        }

        return BouncerFacade::can("send-{$this->abilityPrefix}", $model)
            && $user->hasCompany($model->company_id);
    }

    /**
     * Determine whether the user can delete multiple models.
     */
    public function deleteMultiple(User $user): bool
    {
        if ($this->hasFullCompanyAccess($user)) {
            return true;
        }

        return BouncerFacade::can("delete-{$this->abilityPrefix}", $this->modelClass);
    }

    /**
     * Company owners have full access to records in their active company.
     * Super admins are not constrained by a company boundary.
     */
    protected function hasFullCompanyAccess(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isOwner()) {
            return false;
        }

        return $model === null
            || (int) $model->company_id === (int) request()->header('company');
    }
}
