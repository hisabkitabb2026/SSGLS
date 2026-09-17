<?php

namespace App\Traits;

use App\Support\SafeOrderBy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Trait HasCompanyScopes
 *
 * Provides shared query scopes that are identical across company-scoped models
 * (Invoice, Estimate, Payment, and future document models).
 *
 * The trait uses $this->getTable() to resolve the correct table name, so each
 * model gets the right prefixed column (e.g. invoices.company_id,
 * estimates.company_id, payments.company_id) without duplicating the scope
 * methods in every model class.
 *
 * Scopes provided:
 * - scopeWhereCompany     : Filter by the active company from the request header.
 * - scopeWhereCompanyId   : Filter by an explicit company ID.
 * - scopeWhereCustomer    : Filter by customer_id.
 * - scopePaginateData     : Paginate or return all records.
 * - scopeWhereOrder       : Safe ORDER BY clause.
 * - resolveRouteBinding   : Company-scoped route model binding.
 *
 * Models that need model-specific filter logic (e.g. Invoice::applyFilters
 * with status/paid_status/template_name) should override applyFilters locally
 * and call the individual scopes from this trait as needed.
 */
trait HasCompanyScopes
{
    /**
     * Scope to the active company from the `company` request header.
     *
     * @param  Builder  $query
     * @return void
     */
    public function scopeWhereCompany($query)
    {
        $query->where($this->getTable().'.company_id', Request::header('company'));
    }

    /**
     * Scope to a specific company ID (useful for jobs/commands without a request header).
     *
     * @param  Builder  $query
     * @param  int  $company
     * @return void
     */
    public function scopeWhereCompanyId($query, $company)
    {
        $query->where($this->getTable().'.company_id', $company);
    }

    /**
     * Scope to a specific customer.
     *
     * @param  Builder  $query
     * @param  int  $customer_id
     * @return void
     */
    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->where($this->getTable().'.customer_id', $customer_id);
    }

    /**
     * Paginate the query, or return all records when $limit === 'all'.
     *
     * @param  Builder  $query
     * @param  int|string  $limit
     * @return LengthAwarePaginator|Collection
     */
    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    /**
     * Apply a safe ORDER BY clause (delegates to SafeOrderBy to prevent SQL injection).
     *
     * @param  Builder  $query
     * @param  string  $orderByField
     * @param  string  $orderBy
     * @return void
     */
    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        SafeOrderBy::apply($query, $orderByField, $orderBy);
    }

    /**
     * Company-scoped route model binding.
     *
     * When the `company` header is present (i.e. API context), the model is
     * resolved within the active company's scope.  Otherwise, falls back to
     * the default parent behaviour (e.g. for public PDF viewer routes).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (Request::header('company')) {
            return $this->where($this->getTable().'.company_id', Request::header('company'))
                ->where($field ?? $this->getKeyName(), $value)
                ->firstOrFail();
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
