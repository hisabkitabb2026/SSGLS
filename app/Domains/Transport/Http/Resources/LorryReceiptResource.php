<?php

namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LorryReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'vehicle_number' => $this->vehicle_number,
            'owner_name' => $this->owner_name,
            'driver_name' => $this->driver_name,
            'from_location' => $this->from_location,
            'to_location' => $this->to_location,
            'freight_amount' => $this->freight_amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
