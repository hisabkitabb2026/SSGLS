<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsolidationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'truck_capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:open,ready,dispatched,completed,cancelled'],
        ];
    }
}
