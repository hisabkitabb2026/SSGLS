<?php

namespace App\Domains\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'type' => 'required|string|in:cash,check,card,bank_transfer'];
    }
}
