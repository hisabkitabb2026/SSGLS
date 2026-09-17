<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTruckServiceScheduleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'truck_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'interval_km' => ['nullable', 'integer', 'min:1', 'required_without:interval_days'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'required_without:interval_km'],
            'last_service_odometer_km' => ['nullable', 'integer', 'min:0'],
            'last_service_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
