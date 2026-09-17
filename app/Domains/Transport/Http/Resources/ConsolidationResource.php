<?php

namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsolidationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'destination' => $this->destination,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'warehouse_items_count' => $this->warehouseItems?->count() ?? 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
