<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_location' => 'nullable|string|max:100',
            'section_name' => 'nullable|string|max:255',
            'date_received' => 'nullable|date',
            'destination_city' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
