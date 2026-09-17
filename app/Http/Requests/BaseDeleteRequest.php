<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base delete request for bulk delete operations.
 *
 * Consolidates the common validation pattern across all delete-many endpoints
 * (DeleteCustomersRequest, DeleteItemsRequest, DeleteEstimatesRequest, etc.).
 *
 * Subclasses should override getTableName() and addCustomRules().
 *
 * Standard validation:
 *  - ids must be required array
 *  - ids.* must exist in the specified table
 *
 * Subclasses can add additional rules (e.g. RelationNotExist) via addCustomRules().
 */
abstract class BaseDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Subclasses should NOT override this; instead override getTableName()
     * and addCustomRules() to customize validation.
     */
    public function rules(): array
    {
        $baseRules = [
            'ids' => ['required', 'array'],
            'ids.*' => [
                'required',
                Rule::exists($this->getTableName(), 'id'),
            ],
        ];

        // Allow subclasses to add custom rules (e.g. RelationNotExist checks)
        $customRules = $this->addCustomRules();

        if (! empty($customRules)) {
            $baseRules['ids.*'] = array_merge($baseRules['ids.*'], $customRules);
        }

        return $baseRules;
    }

    /**
     * Get the database table name for this resource.
     *
     * Example: 'customers', 'items', 'invoices'
     */
    abstract protected function getTableName(): string;

    /**
     * Add custom validation rules for IDs.
     *
     * Override in subclasses to add rules like RelationNotExist.
     * Return an array of additional Rule objects.
     *
     * Example (in DeleteItemsRequest):
     *  return [
     *      new RelationNotExist(Item::class, 'invoiceItems'),
     *      new RelationNotExist(Item::class, 'estimateItems'),
     *  ];
     */
    protected function addCustomRules(): array
    {
        return [];
    }
}
