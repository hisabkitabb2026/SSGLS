<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Quotation Station Resource for the Customer Portal.
 *
 * Exposes station name, sequence, and rates (capacity + rate per capacity)
 * for estimates/quotations that use the station-based rate model.
 */
class QuotationStationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'estimate_id' => $this->estimate_id,
            'name' => $this->name,
            'sequence' => $this->sequence,
            'rates' => $this->whenLoaded('rates', fn () => $this->rates->map(fn ($rate) => [
                'id' => $rate->id,
                'capacity' => $rate->capacity,
                'rate' => $rate->rate,
                'formatted_rate' => $rate->formatted_rate,
            ])),
        ];
    }
}
