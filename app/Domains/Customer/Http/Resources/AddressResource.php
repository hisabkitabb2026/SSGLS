<?php

namespace App\Domains\Customer\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
