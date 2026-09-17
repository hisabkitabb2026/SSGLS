<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Load Trip Resource for the Customer Portal.
 *
 * Shows truck dispatch tracking — status, dates, driver, and destination.
 * Customer can track their goods from dispatch to delivery.
 */
class LoadTripResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'trip_number' => $this->trip_number,
            'truck_id' => $this->truck_id,
            'truck_number' => $this->whenLoaded('truck', fn () => $this->truck?->registration_number),
            'driver_name' => $this->whenLoaded('driverProfile', fn () => $this->driverProfile?->name),
            'driver_phone' => $this->whenLoaded('driverProfile', fn () => $this->driverProfile?->phone),
            'broker_name' => $this->whenLoaded('brokerProfile', fn () => $this->brokerProfile?->name),
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,
            'status' => $this->status,
            'dispatch_date' => $this->dispatch_date,
            'expected_delivery_date' => $this->expected_delivery_date,
            'actual_delivery_date' => $this->actual_delivery_date,
            'consolidation_group_id' => $this->consolidation_group_id,
            'notes' => $this->notes,
            'warehouse_items' => $this->whenLoaded('warehouseItems', fn () => WarehouseItemResource::collection($this->warehouseItems)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
