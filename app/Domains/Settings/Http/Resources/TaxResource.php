<?php

namespace App\Domains\Settings\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'rate' => $this->rate, 'company_id' => $this->company_id];
    }
}
