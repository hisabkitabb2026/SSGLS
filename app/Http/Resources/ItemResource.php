<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
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
            'description' => $this->description,
            'price' => $this->price,
            'unit_id' => $this->unit_id,
            'truck_type' => $this->truck_type,
            'rate_card' => $this->rate_card,
            'company_id' => $this->company_id,
            'creator_id' => $this->creator_id,
            'currency_id' => $this->currency_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'tax_per_item' => $this->tax_per_item,
            'formatted_created_at' => $this->formattedCreatedAt,
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($this->unit)),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'taxes' => $this->whenLoaded('taxes', fn () => TaxResource::collection($this->taxes)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
