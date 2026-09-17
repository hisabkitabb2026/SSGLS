<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LorryPartyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:OWNER,DRIVER,BROKER'],
            'name' => ['required', 'string', 'min:3'],
            'code' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'alternate_phone' => ['nullable', 'string'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:15'],
            'bank_name' => ['nullable', 'string'],
            'bank_account_holder_name' => ['nullable', 'string'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,blacklisted'],
            'notes' => ['nullable', 'string'],
            'place' => ['nullable', 'string'],
            'bank_account_no' => ['nullable', 'string'],
            'licence_no' => ['nullable', 'string'],
            'licence_date' => ['nullable', 'date'],
            'licence_issued_by' => ['nullable', 'string'],
            'rto_address' => ['nullable', 'string'],
            'valid_up_to' => ['nullable', 'date'],
            'advice_no' => ['nullable', 'string'],
            'advice_date' => ['nullable', 'date'],
            'destination_broker_name' => ['nullable', 'string'],
            'destination_broker_address' => ['nullable', 'string'],
            'rc_front_path' => ['nullable', 'string'],
            'rc_back_path' => ['nullable', 'string'],
            'pan_front_path' => ['nullable', 'string'],
            'insurance_path' => ['nullable', 'string'],
            'license_front_path' => ['nullable', 'string'],
            'license_back_path' => ['nullable', 'string'],
            'pan_front_path_broker' => ['nullable', 'string'],
        ];
    }

    public function getProfilePayload(): array
    {
        return collect($this->except('company_id'))
            ->merge([
                'company_id' => $this->header('company'),
            ])
            ->toArray();
    }
}
