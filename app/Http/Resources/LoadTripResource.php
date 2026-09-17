<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoadTripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'consolidation_group_id' => $this->consolidation_group_id,
            'truck_id' => $this->truck_id,
            'driver_profile_id' => $this->driver_profile_id,
            'broker_profile_id' => $this->broker_profile_id,
            'trip_number' => $this->trip_number,
            'truck_number' => $this->truck_number,
            'driver_name' => $this->driver_name,
            'driver_phone' => $this->driver_phone,
            'broker_name' => $this->broker_name,
            'broker_phone' => $this->broker_phone,
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,

            'dispatch_date' => $this->dispatch_date?->format('Y-m-d H:i:s'),
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'actual_delivery_date' => $this->actual_delivery_date?->format('Y-m-d'),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'consolidation_group' => new ConsolidationGroupResource($this->whenLoaded('consolidationGroup')),
            'truck' => new TruckResource($this->whenLoaded('truck')),
            'driver_profile' => new LorryPartyProfileResource($this->whenLoaded('driverProfile')),
            'broker_profile' => new LorryPartyProfileResource($this->whenLoaded('brokerProfile')),
            'warehouse_items' => WarehouseItemResource::collection($this->whenLoaded('warehouseItems')),

        ];
    }
}
