<?php

namespace App\Domains\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'customer_type' => 'sometimes|nullable|string|in:individual,business',
            'active' => 'sometimes|boolean',
        ];
    }
}
