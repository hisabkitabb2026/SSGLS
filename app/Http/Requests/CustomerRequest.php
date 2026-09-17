<?php

namespace App\Http\Requests;

use App\Models\Address;
use App\Models\Currency;
use App\Rules\IdnEmail;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Customer create/update request.
 *
 * Extends BaseAddressRequest to consolidate address field validation
 * that is shared across multiple requests.
 */
class CustomerRequest extends BaseAddressRequest
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
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required'],
            'email' => [
                new IdnEmail,
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company')),
            ],
            'password' => ['nullable'],
            'phone' => ['nullable'],
            'company_name' => ['nullable'],
            'contact_name' => ['nullable'],
            'website' => ['nullable'],
            'prefix' => ['nullable'],
            'tax_id' => ['nullable'],
            'enable_portal' => ['boolean'],
            'currency_id' => ['nullable'],
        ];

        // Add address validation rules from base class
        $rules = array_merge($rules, $this->getAddressRules('billing'), $this->getAddressRules('shipping'));

        if ($this->isMethod('PUT') && $this->email != null) {
            $rules['email'] = [
                new IdnEmail,
                'nullable',
                Rule::unique('customers')->where('company_id', $this->header('company'))->ignore($this->route('customer')->id),
            ];
        }

        return $rules;
    }

    public function getCustomerPayload()
    {
        $payload = collect($this->validated())
            ->only([
                'name',
                'email',
                'currency_id',
                'password',
                'phone',
                'prefix',
                'tax_id',
                'company_name',
                'contact_name',
                'website',
                'enable_portal',
                'estimate_prefix',
                'payment_prefix',
                'invoice_prefix',
            ])
            ->merge([
                'creator_id' => $this->user()->id,
                'company_id' => $this->header('company'),
            ]);

        // When Invoicing is disabled (Party mode), auto-set currency to INR
        // if the frontend didn't send one.
        if (! $payload->has('currency_id') || ! $payload->get('currency_id')) {
            $inrCurrency = Currency::where('code', 'INR')->first();
            if ($inrCurrency) {
                $payload['currency_id'] = $inrCurrency->id;
            }
        }

        return $payload->toArray();
    }

    public function getShippingAddress(): array
    {
        return collect($this->shipping)
            ->merge([
                'type' => Address::SHIPPING_TYPE,
            ])
            ->toArray();
    }

    public function getBillingAddress(): array
    {
        return collect($this->billing)
            ->merge([
                'type' => Address::BILLING_TYPE,
            ])
            ->toArray();
    }

    public function hasAddress(array $address)
    {
        $data = Arr::where($address, function ($value, $key) {
            return isset($value);
        });

        return $data;
    }
}
