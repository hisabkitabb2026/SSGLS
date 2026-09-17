<?php

namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLorryReceiptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_number' => 'required|string|max:50',
            'owner_name' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
            'freight_amount' => 'required|numeric|min:0',
        ];
    }
}
