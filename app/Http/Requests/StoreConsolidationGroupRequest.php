<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsolidationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert empty strings to null before validation so that
     * nullable|numeric rules don't fail on empty form inputs.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(function ($value) {
            return $value === '' ? null : $value;
        }, $this->all()));
    }

    public function rules(): array
    {
        return [
            'destination_city' => ['required', 'string', 'max:255'],
            'truck_capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
