<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckOdometerReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'truck_id' => $this->truck_id, 'reading_km' => $this->reading_km, 'recorded_at' => $this->recorded_at, 'notes' => $this->notes, 'image_url' => $this->getFirstMedia('odometer_reading_image') ? "/api/v1/truck-odometer-readings/{$this->id}/image" : null, 'created_at' => $this->created_at];
    }
}
