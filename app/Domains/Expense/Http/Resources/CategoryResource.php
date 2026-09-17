<?php

namespace App\Domains\Expense\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'company_id' => $this->company_id];
    }
}
