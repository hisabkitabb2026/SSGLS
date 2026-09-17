<?php

namespace App\Domains\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers',
            'phone' => 'sometimes|nullable|string|max:20',
            'customer_type' => 'sometimes|nullable|string|in:individual,business',
        ];
    }
}
