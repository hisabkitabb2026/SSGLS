<?php

namespace App\Domains\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'symbol' => 'required|string|max:10'];
    }
}
