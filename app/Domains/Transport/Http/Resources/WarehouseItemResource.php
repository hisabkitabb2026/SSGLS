<?php

namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lr_id' => $this->lr_id,
            'warehouse_location' => $this->warehouse_location,
            'destination' => $this->destination,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
