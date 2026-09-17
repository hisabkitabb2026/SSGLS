<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitWarehouseItemRequest extends FormRequest
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
            'weight_kg' => ['required', 'numeric', 'gt:0'],
            'no_of_packages' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
