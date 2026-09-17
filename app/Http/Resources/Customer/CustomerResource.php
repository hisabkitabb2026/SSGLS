<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'contact_name' => $this->contact_name,
            'company_name' => $this->company_name,
            'website' => $this->website,
            'enable_portal' => $this->enable_portal,
            'type' => $this->type,
            'currency_id' => $this->currency_id,
            'company_id' => $this->company_id,
            'facebook_id' => $this->facebook_id,
            'google_id' => $this->google_id,
            'github_id' => $this->github_id,
            'formatted_created_at' => $this->formattedCreatedAt,
            'avatar' => $this->avatar,
            'prefix' => $this->prefix,
            'tax_id' => $this->tax_id,
            'billing' => $this->whenLoaded('billingAddress', fn () => new AddressResource($this->billingAddress)),
            'shipping' => $this->whenLoaded('shippingAddress', fn () => new AddressResource($this->shippingAddress)),
            'fields' => $this->whenLoaded('fields', fn () => CustomFieldValueResource::collection($this->fields)),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
