<?php

namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LorryPartyProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'phone' => $this->phone,
            'created_at' => $this->created_at,
        ];
    }
}
