<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoadTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:pending,in_transit,completed',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
