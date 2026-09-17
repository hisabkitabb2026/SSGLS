<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'discount_type' => $this->discount_type,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'unit_name' => $this->unit_name,
            'discount' => $this->discount,
            'discount_val' => $this->discount_val,
            'tax' => $this->tax,
            'total' => $this->total,
            'invoice_id' => $this->invoice_id,
            'item_id' => $this->item_id,
            'company_id' => $this->company_id,
            'base_price' => $this->base_price,
            'exchange_rate' => $this->exchange_rate,
            'base_discount_val' => $this->base_discount_val,
            'base_tax' => $this->base_tax,
            'base_total' => $this->base_total,
            'recurring_invoice_id' => $this->recurring_invoice_id,

            // Office Invoice item-level transport fields (native columns)
            'consignment_number' => $this->consignment_number,
            'consignment_date' => $this->consignment_date,
            'party_inv_no' => $this->party_inv_no,
            'from_code' => $this->from_code,

            'from_name' => $this->from_name,
            'to_code' => $this->to_code,
            'to_name' => $this->to_name,
            'truck_no' => $this->truck_no,
            'pkg' => $this->pkg,
            'weight' => $this->weight,
            'rate' => $this->rate,

            'other_charge' => $this->other_charge,
            'lr_charge' => $this->lr_charge,
            'dd_charge' => $this->dd_charge,
            'amount' => $this->amount,

            'taxes' => $this->whenLoaded('taxes', fn () => TaxResource::collection($this->taxes)),
            'fields' => $this->whenLoaded('fields', fn () => CustomFieldValueResource::collection($this->fields)),
        ];
    }
}
