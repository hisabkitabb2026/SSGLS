<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsolidationGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'group_number' => $this->group_number,
            'destination_city' => $this->destination_city,
            'status' => $this->status,
            'total_weight_kg' => (float) $this->total_weight_kg,
            'total_packages' => $this->total_packages,
            'total_items' => $this->total_items,
            'truck_capacity_kg' => (float) $this->truck_capacity_kg,
            'fill_percentage' => $this->fill_percentage,
            'is_ready_to_dispatch' => $this->is_ready_to_dispatch,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'items' => WarehouseItemResource::collection($this->whenLoaded('items')),
            'load_trips' => LoadTripResource::collection($this->whenLoaded('loadTrips')),
        ];
    }
}
