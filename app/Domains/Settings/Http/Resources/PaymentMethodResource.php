<?php

namespace App\Domains\Settings\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'type' => $this->type, 'company_id' => $this->company_id];
    }
}
