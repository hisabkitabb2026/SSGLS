<?php

namespace App\Domains\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'street_address' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'postal_code' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:100',
            'is_primary' => 'sometimes|boolean',
        ];
    }
}
