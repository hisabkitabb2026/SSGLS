<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'sometimes|string',
            'destination' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'weight' => 'sometimes|numeric|min:0',
        ];
    }
}
