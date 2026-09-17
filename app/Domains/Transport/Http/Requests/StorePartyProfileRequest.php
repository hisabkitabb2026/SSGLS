<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartyProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:owner,driver,broker',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:lorry_party_profiles',
            'address' => 'sometimes|string',
            'phone' => 'sometimes|string|max:20',
        ];
    }
}
