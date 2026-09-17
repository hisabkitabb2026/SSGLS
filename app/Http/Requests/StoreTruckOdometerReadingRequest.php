<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTruckOdometerReadingRequest extends FormRequest
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
            'reading_km' => ['required', 'integer', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'reading_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
