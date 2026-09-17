<?php

namespace App\Domains\Settings\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray($request)
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'symbol' => $this->symbol];
    }
}
