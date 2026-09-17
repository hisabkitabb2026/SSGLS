<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTruckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'owner_profile_id' => ['nullable', 'integer'],
            'truck_number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('trucks')
                    ->where('company_id', $this->header('company'))
                    ->ignore($this->route('id')),
            ],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'make' => ['nullable', 'string', 'max:255'],
            'vehicle_model' => ['nullable', 'string', 'max:255'],
            'registered_at' => ['nullable', 'string', 'max:255'],
            'colour' => ['nullable', 'string', 'max:255'],
            'capacity_kg' => ['sometimes', 'numeric', 'gt:0'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'engine_number' => ['nullable', 'string', 'max:255'],

            'status' => ['nullable', 'in:available,reserved,on_trip,maintenance,inactive'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
