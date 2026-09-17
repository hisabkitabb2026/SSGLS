<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for forms that include address fields (billing/shipping).
 *
 * Consolidates the common address validation pattern that appears in multiple
 * requests (CustomerRequest, EstimatesRequest, InvoicesRequest, etc.).
 *
 * Standard address field structure:
 *  - billing.name, billing.address_street_1, billing.address_street_2
 *  - billing.city, billing.state, billing.country_id, billing.zip
 *  - billing.phone, billing.fax
 *
 * Subclasses can use getAddressRules() to add these rules to their form rules.
 */
abstract class BaseAddressRequest extends FormRequest
{
    /**
     * Get the validation rules for billing/shipping address fields.
     *
     * Returns an associative array of field patterns to their validation rules.
     *
     * Usage in subclass:
     *  public function rules(): array
     *  {
     *      return array_merge(
     *          $this->getAddressRules('billing'),
     *          $this->getAddressRules('shipping'),
     *          [...other rules...]
     *      );
     *  }
     */
    protected function getAddressRules(string $addressType = 'billing'): array
    {
        return [
            "{$addressType}.name" => ['nullable'],
            "{$addressType}.address_street_1" => ['nullable'],
            "{$addressType}.address_street_2" => ['nullable'],
            "{$addressType}.city" => ['nullable'],
            "{$addressType}.state" => ['nullable'],
            "{$addressType}.country_id" => ['nullable'],
            "{$addressType}.zip" => ['nullable'],
            "{$addressType}.phone" => ['nullable'],
            "{$addressType}.fax" => ['nullable'],
        ];
    }

    /**
     * Helper to extract billing address from validated data.
     *
     * Usage: $billingAddress = $this->getBillingAddress();
     */
    public function getBillingAddress(): array
    {
        return collect($this->billing ?? [])
            ->merge(['type' => 'billing'])
            ->toArray();
    }

    /**
     * Helper to extract shipping address from validated data.
     *
     * Usage: $shippingAddress = $this->getShippingAddress();
     */
    public function getShippingAddress(): array
    {
        return collect($this->shipping ?? [])
            ->merge(['type' => 'shipping'])
            ->toArray();
    }
}
