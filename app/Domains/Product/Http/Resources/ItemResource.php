<?php

namespace App\Domains\Product\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'description' => $this->description, 'price' => $this->price, 'unit_id' => $this->unit_id, 'created_at' => $this->created_at];
    }
}
