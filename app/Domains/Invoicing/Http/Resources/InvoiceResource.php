<?php

namespace App\Domains\Invoicing\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'notes' => $this->notes,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
