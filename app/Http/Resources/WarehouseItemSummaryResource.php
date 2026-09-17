<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'destination' => $this->resource['destination'],
            'total_items' => $this->resource['total_items'],
            'total_boxes' => $this->resource['total_boxes'],
            'total_weight' => $this->resource['total_weight'],
            'total_revenue' => $this->resource['total_revenue'],
            'items' => WarehouseItemResource::collection($this->resource['items']),
        ];
    }
}
