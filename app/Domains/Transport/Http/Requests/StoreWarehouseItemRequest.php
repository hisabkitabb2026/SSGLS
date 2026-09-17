<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lr_id' => 'required|exists:invoices,id',
            'warehouse_location' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'weight' => 'required|numeric|min:0',
            'status' => 'sometimes|string',
        ];
    }
}
