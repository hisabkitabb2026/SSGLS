<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lr_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where('company_id', $this->header('company')),
            ],
            'warehouse_location' => 'nullable|string|max:100',
            'section_name' => 'nullable|string|max:255',
            'date_received' => 'nullable|date',
            'destination_city' => 'nullable|string|max:255',
            'load_type' => 'nullable|string|in:full_load,part_load',
            'promised_dispatch_date' => 'nullable|date',
            'weight_kg' => 'nullable|numeric|min:0',
            'no_of_packages' => 'nullable|integer|min:0',
            'goods_description' => 'nullable|string',
            'consignor_name' => 'nullable|string|max:255',
            'consignee_name' => 'nullable|string|max:255',
            'priority' => 'nullable|string|in:normal,urgent,critical',
            'notes' => 'nullable|string',
        ];
    }
}
