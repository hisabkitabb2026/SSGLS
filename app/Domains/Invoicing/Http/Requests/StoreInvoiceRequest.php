<?php

namespace App\Domains\Invoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'invoice_number' => 'required|string|max:255|unique:invoices',
            'notes' => 'sometimes|nullable|string',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
        ];
    }
}
