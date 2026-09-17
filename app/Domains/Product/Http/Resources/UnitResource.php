<?php

namespace App\Domains\Product\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'symbol' => $this->symbol];
    }
}
