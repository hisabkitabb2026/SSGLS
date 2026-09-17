<?php

namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LoadTripResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'consolidation_id' => $this->consolidation_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
