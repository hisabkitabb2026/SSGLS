<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LorryPartyProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'code' => $this->code,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'pan_number' => $this->pan_number,
            'gstin' => $this->gstin,
            'bank_name' => $this->bank_name,
            'bank_account_holder_name' => $this->bank_account_holder_name,
            'ifsc_code' => $this->ifsc_code,
            'upi_id' => $this->upi_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'place' => $this->place,
            'licence_no' => $this->licence_no,
            'licence_date' => $this->licence_date,
            'licence_issued_by' => $this->licence_issued_by,
            'rto_address' => $this->rto_address,
            'valid_up_to' => $this->valid_up_to,
            'advice_no' => $this->advice_no,
            'advice_date' => $this->advice_date,
            'destination_broker_name' => $this->destination_broker_name,
            'destination_broker_address' => $this->destination_broker_address,
            'bank_account_no' => $this->bank_account_no,
            'rc_front_path' => $this->rc_front_path,
            'rc_back_path' => $this->rc_back_path,
            'pan_front_path' => $this->pan_front_path,
            'insurance_path' => $this->insurance_path,
            'license_front_path' => $this->license_front_path,
            'license_back_path' => $this->license_back_path,
            'pan_front_path_broker' => $this->pan_front_path_broker,
            'created_at' => $this->created_at,
            'formatted_created_at' => optional($this->created_at)->format('d M Y'),
            'customer' => $this->when($this->relationLoaded('customer') && $this->customer, function () {
                return new CustomerResource($this->customer);
            }),
        ];
    }
}
