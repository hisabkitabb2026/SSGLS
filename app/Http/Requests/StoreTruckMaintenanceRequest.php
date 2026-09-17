<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTruckMaintenanceRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255'],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed,cancelled'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
