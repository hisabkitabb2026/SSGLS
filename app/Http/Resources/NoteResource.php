<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
        ];
    }
}
