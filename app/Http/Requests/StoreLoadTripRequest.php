<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoadTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Convert empty strings to null before validation.
     *
     * The frontend form initialises integer and date fields as empty strings ('').
     * Without this, 'nullable|integer' and 'nullable|date' rules reject '' because
     * an empty string is not null — it fails the integer/date rule.
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
            'consolidation_group_id' => ['nullable', 'integer'],
            'truck_id' => ['nullable', 'integer'],
            'driver_profile_id' => ['nullable', 'integer'],
            'truck_number' => ['nullable', 'string', 'max:255'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:255'],
            'broker_profile_id' => ['nullable', 'integer'],
            'broker_name' => ['nullable', 'string', 'max:255'],
            'broker_phone' => ['nullable', 'string', 'max:255'],
            'origin_city' => ['nullable', 'string', 'max:255'],
            'destination_city' => ['required', 'string', 'max:255'],
            'dispatch_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
