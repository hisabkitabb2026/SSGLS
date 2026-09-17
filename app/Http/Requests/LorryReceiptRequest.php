<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for Lorry Receipt create/update.
 *
 * Lorry Receipts are transport documents with owner/driver/broker party
 * details, vehicle information, and Section C/D/E payment fields.
 * Many fields are nullable because the form is filled in stages
 * (Section C at creation, Section E at final settlement).
 */
class LorryReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Core identifiers
            'company_id' => ['nullable', 'integer'],
            'creator_id' => ['nullable', 'integer'],
            'updated_by' => ['nullable', 'integer'],

            // Vehicle & route
            'challan_no' => ['nullable', 'string', 'max:255'],
            'contract_no' => ['nullable', 'string', 'max:255'],
            'lorry_no' => ['nullable', 'string', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'to_name' => ['nullable', 'string', 'max:255'],
            'from_code' => ['nullable', 'string', 'max:255'],
            'to_code' => ['nullable', 'string', 'max:255'],

            // Party references (Customer FKs)
            'owner_customer_id' => ['nullable', 'integer'],
            'driver_customer_id' => ['nullable', 'integer'],
            'broker_customer_id' => ['nullable', 'integer'],

            // Party profile reference (LorryPartyProfile FK — replaces Customer
            // for the "Party" field on Lorry Receipts)
            'party_profile_id' => ['nullable', 'integer'],

            // Denormalized party details (auto-filled from Customer records)
            'owner_name' => ['nullable', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:255'],
            'owner_address' => ['nullable', 'string'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_address' => ['nullable', 'string'],
            'broker_name' => ['nullable', 'string', 'max:255'],
            'broker_phone' => ['nullable', 'string', 'max:255'],
            'broker_address' => ['nullable', 'string'],

            // Section C — Advance payment
            'paid_to' => ['nullable', 'string', 'max:255'],
            'lorry_hire_amount' => ['nullable', 'string', 'max:255'],
            'other_charges_amount' => ['nullable', 'string', 'max:255'],
            'advance_cash_cheque_no' => ['nullable', 'string', 'max:255'],
            'advance_on' => ['nullable', 'string', 'max:255'],
            'advance_bank' => ['nullable', 'string', 'max:255'],
            'advance_amount' => ['nullable', 'string', 'max:255'],
            'balance_payable_at' => ['nullable', 'string', 'max:255'],
            'loaded_by' => ['nullable', 'string', 'max:255'],

            // Section D — Consignment details
            'received_no_bilties' => ['nullable', 'string', 'max:255'],
            'gross_hire_rupees' => ['nullable', 'string', 'max:255'],
            'net_amount_payable' => ['nullable', 'string', 'max:255'],

            // Section E — Final settlement
            'final_paid_to' => ['nullable', 'string', 'max:255'],
            'detention_amount' => ['nullable', 'string', 'max:255'],
            'extra_hire_amount' => ['nullable', 'string', 'max:255'],
            'final_other_amount' => ['nullable', 'string', 'max:255'],
            'less_advance_other_branch_amount' => ['nullable', 'string', 'max:255'],
            'less_deduction_claims_amount' => ['nullable', 'string', 'max:255'],
            'final_balance_paid_at' => ['nullable', 'string', 'max:255'],
            'final_balance_on' => ['nullable', 'string', 'max:255'],
            'final_cash_cheque_no' => ['nullable', 'string', 'max:255'],
            'final_bank' => ['nullable', 'string', 'max:255'],

            // Vehicle details
            'body_type' => ['nullable', 'string', 'max:255'],
            'make' => ['nullable', 'string', 'max:255'],
            'vehicle_model' => ['nullable', 'string', 'max:255'],
            'colour' => ['nullable', 'string', 'max:255'],
            'chasis_no' => ['nullable', 'string', 'max:255'],
            'engine_no' => ['nullable', 'string', 'max:255'],

            // Owner/Financer details
            'owner_address' => ['nullable', 'string'],
            'owner_phone' => ['nullable', 'string', 'max:255'],
            'owner_bank_account_no' => ['nullable', 'string', 'max:255'],
            'owner_pan_no' => ['nullable', 'string', 'max:255'],
            'financer_address' => ['nullable', 'string'],

            // Driver details
            'driver_place' => ['nullable', 'string', 'max:255'],
            'driver_licence_no' => ['nullable', 'string', 'max:255'],
            'driver_licence_date' => ['nullable', 'string', 'max:255'],
            'driver_licence_issued_by' => ['nullable', 'string', 'max:255'],
            'driver_rto_address' => ['nullable', 'string'],
            'driver_valid_up_to' => ['nullable', 'string', 'max:255'],
            'driver_bank_account_no' => ['nullable', 'string', 'max:255'],

            // Broker details
            'broker_pan_no' => ['nullable', 'string', 'max:255'],
            'broker_phone_no' => ['nullable', 'string', 'max:255'],
            'broker_bank_account_no' => ['nullable', 'string', 'max:255'],
            'broker_address' => ['nullable', 'string'],

            // Destination broker
            'advice_date' => ['nullable', 'string', 'max:255'],
            'destination_broker_name' => ['nullable', 'string', 'max:255'],
            'destination_broker_address' => ['nullable', 'string'],

            // Metadata
            'date_created' => ['nullable', 'string', 'max:255'],
            'date_modified' => ['nullable', 'string', 'max:255'],
            'modified_dates' => ['nullable', 'array'],
        ];
    }
}
