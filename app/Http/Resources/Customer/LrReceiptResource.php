<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LR Receipt Resource for the Customer Portal.
 *
 * Exposes transport-specific fields for invoices with template_name = 'lr_receipt'.
 * Shows docket number, route, consignee, items, and payment summary.
 */
class LrReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'reference_number' => $this->reference_number,
            'invoice_date' => $this->invoice_date,
            'formatted_invoice_date' => $this->formattedInvoiceDate,
            'status' => $this->status,
            'derived_status' => $this->derived_status ?? $this->status,
            'paid_status' => $this->paid_status,

            'total' => $this->total,
            'due_amount' => $this->due_amount,
            'sub_total' => $this->sub_total,
            'tax' => $this->tax,
            'template_name' => $this->template_name,
            'unique_hash' => $this->unique_hash,
            'overdue' => $this->overdue,
            'currency_id' => $this->currency_id,
            'invoice_pdf_url' => $this->invoicePdfUrl,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'consignee_customer' => $this->whenLoaded('consigneeCustomer', fn () => new CustomerResource($this->consigneeCustomer)),
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
