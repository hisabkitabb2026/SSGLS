<?php

namespace App\Domains\Customer\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
            'active' => $this->active,
            'company_id' => $this->company_id,
            'billing' => $this->whenLoaded('billingAddress', fn () => new AddressResource($this->billingAddress)),
            'shipping' => $this->whenLoaded('shippingAddress', fn () => new AddressResource($this->shippingAddress)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
