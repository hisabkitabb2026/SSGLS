<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lorry Receipt Resource for the Customer Portal.
 *
 * Exposes transport-specific fields for invoices with template_name = 'lorry_receipt'.
 * Shows lorry number, contract, owner/driver/broker, advance and final payment sections.
 */
class LorryReceiptResource extends JsonResource
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

            // Section A — Vehicle & Party
            'lorry_no' => $this->lorry_no,
            'contract_no' => $this->contract_no,
            'owner_name' => $this->owner_name,
            'driver_name' => $this->driver_name,
            'broker_name' => $this->broker_name,
            'from_name' => $this->from_name,
            'to_name' => $this->to_name,

            // Section B — Consignment
            'received_no_bilties' => $this->received_no_bilties,

            // Section C — Advance Payment
            'advance_amount' => $this->advance_amount,
            'paid_to' => $this->paid_to,

            // Section E — Final Payment
            'net_amount_payable' => $this->net_amount_payable,
            'detention_amount' => $this->detention_amount,
            'extra_hire_amount' => $this->extra_hire_amount,
            'final_other_amount' => $this->final_other_amount,
            'less_deduction_claims_amount' => $this->less_deduction_claims_amount,

            // Relations
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'owner_customer' => $this->whenLoaded('ownerCustomer', fn () => new CustomerResource($this->ownerCustomer)),
            'driver_customer' => $this->whenLoaded('driverCustomer', fn () => new CustomerResource($this->driverCustomer)),
            'broker_customer' => $this->whenLoaded('brokerCustomer', fn () => new CustomerResource($this->brokerCustomer)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
