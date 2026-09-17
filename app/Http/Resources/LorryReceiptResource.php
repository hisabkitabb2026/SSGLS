<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LorryReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'unique_hash' => $this->unique_hash,
            'company_id' => $this->company_id,
            'creator_id' => $this->creator_id,

            // Party references
            'owner_customer_id' => $this->owner_customer_id,
            'driver_customer_id' => $this->driver_customer_id,
            'broker_customer_id' => $this->broker_customer_id,
            'party_profile_id' => $this->party_profile_id,

            // Contract & Route
            'contract_no' => $this->contract_no,
            'from_code' => $this->from_code,
            'from_name' => $this->from_name,
            'to_code' => $this->to_code,
            'to_name' => $this->to_name,
            'challan_no' => $this->challan_no,

            // Shipment details
            'no_of_pages' => $this->no_of_pages,
            'no_of_pkgs' => $this->no_of_pkgs,
            'actual_weight' => $this->actual_weight,
            'charge_weight' => $this->charge_weight,
            'lorry_no' => $this->lorry_no,
            'rate' => $this->rate,
            'distance_kms' => $this->distance_kms,

            // Registration
            'regd_at' => $this->regd_at,

            // Vehicle details
            'body_type' => $this->body_type,
            'make' => $this->make,
            'vehicle_model' => $this->vehicle_model,
            'colour' => $this->colour,
            'chasis_no' => $this->chasis_no,
            'engine_no' => $this->engine_no,

            // Fitness & Permit
            'fitness_validity' => $this->fitness_validity,
            'road_permit_no' => $this->road_permit_no,
            'permit_date' => $this->permit_date,
            'permit_valid_in' => $this->permit_valid_in,
            'permit_status_upto' => $this->permit_status_upto,

            // Insurance
            'insured_with' => $this->insured_with,
            'insurance_division_no' => $this->insurance_division_no,
            'insurance_certificate_no' => $this->insurance_certificate_no,
            'insurance_valid_upto' => $this->insurance_valid_upto,

            // Owner details
            'owner_code' => $this->owner_code,
            'owner_name' => $this->owner_name,
            'owner_address' => $this->owner_address,
            'owner_phone' => $this->owner_phone,
            'owner_bank_account_no' => $this->owner_bank_account_no,

            // Financer
            'financer_name' => $this->financer_name,
            'financer_address' => $this->financer_address,

            // Driver details
            'driver_name' => $this->driver_name,
            'driver_address' => $this->driver_address,
            'driver_place' => $this->driver_place,
            'driver_licence_no' => $this->driver_licence_no,
            'driver_licence_date' => $this->driver_licence_date,
            'driver_licence_issued_by' => $this->driver_licence_issued_by,
            'driver_rto_address' => $this->driver_rto_address,
            'driver_valid_up_to' => $this->driver_valid_up_to,
            'driver_bank_account_no' => $this->driver_bank_account_no,

            // Broker details
            'broker_name' => $this->broker_name,
            'broker_address' => $this->broker_address,
            'broker_phone' => $this->broker_phone,
            'broker_bank_account_no' => $this->broker_bank_account_no,

            // Advice
            'advice_no' => $this->advice_no,
            'advice_date' => $this->advice_date,

            // Destination broker
            'destination_broker_name' => $this->destination_broker_name,
            'destination_broker_address' => $this->destination_broker_address,

            // Hire & Payment - Initial
            'paid_to' => $this->paid_to,
            'lorry_hire_amount' => $this->lorry_hire_amount,
            'other_charges_amount' => $this->other_charges_amount,
            'gross_hire_rupees' => $this->gross_hire_rupees,
            'gross_hire_amount' => $this->gross_hire_amount,

            // Advance
            'advance_cash_cheque_no' => $this->advance_cash_cheque_no,
            'advance_on' => $this->advance_on,
            'advance_bank' => $this->advance_bank,
            'advance_amount' => $this->advance_amount,

            // Balance
            'balance_payable_at' => $this->balance_payable_at,
            'balance_payable_code' => $this->balance_payable_code,
            'balance_rupees' => $this->balance_rupees,
            'balance_amount' => $this->balance_amount,
            'balance_rupees_only' => $this->balance_rupees_only,

            // Authorization
            'hire_passed_by' => $this->hire_passed_by,
            'hire_certified_by' => $this->hire_certified_by,
            'hire_prepared_by' => $this->hire_prepared_by,
            'advance_received_by' => $this->advance_received_by,

            // Loading
            'loading_remarks' => $this->loading_remarks,
            'loaded_by' => $this->loaded_by,

            // Final settlement
            'final_paid_to' => $this->final_paid_to,
            'detention_amount' => $this->detention_amount,
            'extra_hire_amount' => $this->extra_hire_amount,
            'final_other_amount' => $this->final_other_amount,
            'final_total_extra_amount' => $this->final_total_extra_amount,
            'grand_total_amount' => $this->grand_total_amount,
            'less_advance_other_branch_amount' => $this->less_advance_other_branch_amount,
            'less_deduction_claims_amount' => $this->less_deduction_claims_amount,
            'total_less_amount' => $this->total_less_amount,

            // Final balance
            'final_balance_paid_at' => $this->final_balance_paid_at,
            'final_balance_code' => $this->final_balance_code,
            'final_balance_on' => $this->final_balance_on,
            'net_amount_payable' => $this->net_amount_payable,
            'final_cash_cheque_no' => $this->final_cash_cheque_no,
            'final_cash_cheque_on' => $this->final_cash_cheque_on,
            'final_bank' => $this->final_bank,
            'final_rupees_only' => $this->final_rupees_only,

            // Final authorization
            'final_passed_by' => $this->final_passed_by,
            'final_certified_by' => $this->final_certified_by,
            'final_prepared_by' => $this->final_prepared_by,
            'final_payment_received_by' => $this->final_payment_received_by,

            // Bilties
            'received_no_bilties' => $this->received_no_bilties,

            // Audit
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'date_created' => $this->date_created,
            'date_modified' => $this->date_modified,
            'modified_dates' => $this->modified_dates,

            // PDF URL
            'lorry_receipt_pdf_url' => $this->lorryReceiptPdfUrl,

            // Computed fields for index table
            'customer_display_name' => $this->customerDisplayName,
            'display_amount_due' => $this->displayAmountDue,
            'total_from_invoices' => $this->totalFromInvoices,

            // Relations
            'owner_customer' => $this->whenLoaded('ownerCustomer', fn () => new CustomerResource($this->ownerCustomer)),
            'driver_customer' => $this->whenLoaded('driverCustomer', fn () => new CustomerResource($this->driverCustomer)),
            'broker_customer' => $this->whenLoaded('brokerCustomer', fn () => new CustomerResource($this->brokerCustomer)),
            'party_profile' => $this->whenLoaded('partyProfile', fn () => [
                'id' => $this->partyProfile->id,
                'name' => $this->partyProfile->name,
                'type' => $this->partyProfile->type,
            ]),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
        ];
    }
}
