<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoadTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'consolidation_id' => 'required|integer|exists:consolidation_groups,id',
            'status' => 'sometimes|string|in:pending,in_transit,completed',
            'notes' => 'sometimes|nullable|string',
        ];
    }
}
