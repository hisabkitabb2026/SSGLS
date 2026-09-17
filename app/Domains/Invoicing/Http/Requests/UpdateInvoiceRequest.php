<?php

namespace App\Domains\Invoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'sometimes|nullable|string',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'total_amount' => 'sometimes|numeric|min:0',
        ];
    }
}
