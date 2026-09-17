<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsolidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'status' => 'sometimes|string|in:pending,in_transit,delivered',
            'warehouse_items' => 'sometimes|array',
            'warehouse_items.*' => 'integer|exists:warehouse_items,id',
        ];
    }
}
