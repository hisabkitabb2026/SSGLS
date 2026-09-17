<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'type' => $this->type,
            'status' => $this->status,
            'transaction_date' => $this->transaction_date,
            'invoice_id' => $this->invoice_id,
            'invoice' => $this->whenLoaded('invoice', fn () => new InvoiceResource($this->invoice)),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
        ];
    }
}
