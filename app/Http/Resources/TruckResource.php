<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_profile_id' => $this->owner_profile_id,
            'truck_number' => $this->truck_number,
            'vehicle_type' => $this->vehicle_type,
            'body_type' => $this->body_type,
            'make' => $this->make,
            'vehicle_model' => $this->vehicle_model,
            'registered_at' => $this->registered_at,
            'colour' => $this->colour,
            'capacity_kg' => (float) $this->capacity_kg,
            'chassis_number' => $this->chassis_number,
            'engine_number' => $this->engine_number,
            'current_odometer_km' => $this->current_odometer_km,

            'status' => $this->status,
            'notes' => $this->notes,
            'owner_profile' => new LorryPartyProfileResource($this->whenLoaded('ownerProfile')),
            'load_trips' => LoadTripResource::collection($this->whenLoaded('loadTrips')),
        ];
    }
}
