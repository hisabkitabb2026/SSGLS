<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckMaintenanceResource extends JsonResource
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
            'truck_id' => $this->truck_id,
            'type' => $this->type,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'completed_date' => $this->completed_date?->toDateString(),
            'status' => $this->status,
            'cost' => $this->cost,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'truck' => TruckResource::make($this->whenLoaded('truck')),
        ];
    }
}
