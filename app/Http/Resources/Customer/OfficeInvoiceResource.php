<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Office Invoice (Invoice Receipt) Resource for the Customer Portal.
 *
 * Exposes transport-specific fields for invoices with template_name = 'office_invoice'.
 * Shows amount_debit, amount_credit, due_amount, and linked LR docket.
 */
class OfficeInvoiceResource extends JsonResource
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
            'paid_status' => $this->paid_status,
            'total' => $this->total,
            'due_amount' => $this->due_amount,
            'template_name' => $this->template_name,
            'unique_hash' => $this->unique_hash,
            'invoice_pdf_url' => $this->invoicePdfUrl,
            'currency_id' => $this->currency_id,

            // Transport-specific fields
            'amount_debit' => $this->amount_debit,
            'amount_credit' => $this->amount_credit,
            'amount_debit_date' => $this->amount_debit_date,
            'amount_credit_date' => $this->amount_credit_date,

            // Relations
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
