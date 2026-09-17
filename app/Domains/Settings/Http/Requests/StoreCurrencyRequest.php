<?php

namespace App\Domains\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required|string|max:100', 'code' => 'required|string|size:3|unique:currencies', 'symbol' => 'required|string|max:5'];
    }
}
