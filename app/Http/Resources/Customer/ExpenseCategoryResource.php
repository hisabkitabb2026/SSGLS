<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'amount' => $this->amount,
            'formatted_created_at' => $this->formattedCreatedAt,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
        ];
    }
}
