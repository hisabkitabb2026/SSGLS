<?php

namespace App\Domains\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:255', 'description' => 'sometimes|nullable|string', 'price' => 'required|numeric|min:0', 'unit_id' => 'required|integer|exists:units,id'];
    }
}
