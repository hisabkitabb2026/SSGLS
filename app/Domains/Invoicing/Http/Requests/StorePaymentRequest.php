<?php

namespace App\Domains\Invoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,check,card,bank_transfer',
            'reference' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
