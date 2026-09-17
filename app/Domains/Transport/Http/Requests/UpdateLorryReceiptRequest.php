<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLorryReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'sometimes|string|in:pending,confirmed,in_transit,delivered,cancelled',
            'vehicle_number' => 'sometimes|string|max:50',
            'freight_amount' => 'sometimes|numeric|min:0',
        ];
    }
}
