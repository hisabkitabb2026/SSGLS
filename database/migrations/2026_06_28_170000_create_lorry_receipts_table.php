<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lorry_receipts', function (Blueprint $table) {
            $table->id();
            // companies.id and users.id are INT UNSIGNED (increments), not
            // BIGINT. Using unsignedBigInteger here causes MySQL error 3780
            // ("Referencing column ... and referenced column ... are
            // incompatible") because the FK type must match the parent PK.
            // customers.id is BIGINT (via $table->id()), so those stay
            // unsignedBigInteger. Per codebase convention we use plain
            // unsignedInteger columns + indexes without DB-level FK constraints.
            $table->unsignedInteger('company_id')->nullable()->index();
            $table->unsignedInteger('creator_id')->nullable()->index();
            $table->unsignedInteger('updated_by')->nullable()->index();

            // Party references (customers.id is BIGINT UNSIGNED)
            $table->unsignedBigInteger('owner_customer_id')->nullable()->index();
            $table->unsignedBigInteger('driver_customer_id')->nullable()->index();
            $table->unsignedBigInteger('broker_customer_id')->nullable()->index();

            $table->string('unique_hash')->nullable()->unique();

            // Contract & Route
            $table->string('contract_no')->nullable();
            $table->string('from_code')->nullable();
            $table->string('from_name')->nullable();
            $table->string('to_code')->nullable();
            $table->string('to_name')->nullable();
            $table->string('challan_no')->nullable();

            // Shipment details
            $table->string('no_of_pages')->nullable();
            $table->string('no_of_pkgs')->nullable();
            $table->string('actual_weight')->nullable();
            $table->string('charge_weight')->nullable();
            $table->string('lorry_no')->nullable();
            $table->string('rate')->nullable();
            $table->string('distance_kms')->nullable();

            // Registration
            $table->text('regd_at')->nullable();

            // Vehicle details
            $table->string('body_type')->nullable();
            $table->string('make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('colour')->nullable();
            $table->string('chasis_no')->nullable();
            $table->string('engine_no')->nullable();

            // Fitness & Permit
            $table->string('fitness_validity')->nullable();
            $table->string('road_permit_no')->nullable();
            $table->string('permit_date')->nullable();
            $table->string('permit_valid_in')->nullable();
            $table->string('permit_status_upto')->nullable();

            // Insurance
            $table->text('insured_with')->nullable();
            $table->text('insurance_division_no')->nullable();
            $table->text('insurance_certificate_no')->nullable();
            $table->text('insurance_valid_upto')->nullable();

            // Owner details
            $table->string('owner_code')->nullable();
            $table->string('owner_name')->nullable();
            $table->text('owner_address')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('owner_bank_account_no')->nullable();

            // Financer
            $table->text('financer_name')->nullable();
            $table->text('financer_address')->nullable();

            // Driver details
            $table->string('driver_name')->nullable();
            $table->text('driver_address')->nullable();
            $table->string('driver_place')->nullable();
            $table->string('driver_licence_no')->nullable();
            $table->string('driver_licence_date')->nullable();
            $table->text('driver_licence_issued_by')->nullable();
            $table->text('driver_rto_address')->nullable();
            $table->string('driver_valid_up_to')->nullable();
            $table->string('driver_bank_account_no')->nullable();

            // Broker details
            $table->string('broker_name')->nullable();
            $table->text('broker_address')->nullable();
            $table->string('broker_phone')->nullable();
            $table->string('broker_bank_account_no')->nullable();

            // Advice
            $table->string('advice_no')->nullable();
            $table->string('advice_date')->nullable();

            // Destination broker
            $table->text('destination_broker_name')->nullable();
            $table->text('destination_broker_address')->nullable();

            // Hire & Payment - Initial
            $table->string('paid_to')->nullable();
            $table->string('lorry_hire_amount')->nullable();
            $table->string('other_charges_amount')->nullable();
            $table->text('gross_hire_rupees')->nullable();
            $table->string('gross_hire_amount')->nullable();

            // Advance
            $table->text('advance_cash_cheque_no')->nullable();
            $table->string('advance_on')->nullable();
            $table->text('advance_bank')->nullable();
            $table->string('advance_amount')->nullable();

            // Balance
            $table->text('balance_payable_at')->nullable();
            $table->string('balance_payable_code')->nullable();
            $table->text('balance_rupees')->nullable();
            $table->string('balance_amount')->nullable();
            $table->text('balance_rupees_only')->nullable();

            // Authorization
            $table->text('hire_passed_by')->nullable();
            $table->text('hire_certified_by')->nullable();
            $table->text('hire_prepared_by')->nullable();
            $table->text('advance_received_by')->nullable();

            // Loading
            $table->text('loading_remarks')->nullable();
            $table->string('loaded_by')->nullable();

            // Final settlement
            $table->string('final_paid_to')->nullable();
            $table->string('detention_amount')->nullable();
            $table->string('extra_hire_amount')->nullable();
            $table->string('final_other_amount')->nullable();
            $table->string('final_total_extra_amount')->nullable();
            $table->string('grand_total_amount')->nullable();
            $table->string('less_advance_other_branch_amount')->nullable();
            $table->string('less_deduction_claims_amount')->nullable();
            $table->string('total_less_amount')->nullable();

            // Final balance
            $table->text('final_balance_paid_at')->nullable();
            $table->string('final_balance_code')->nullable();
            $table->string('final_balance_on')->nullable();
            $table->string('net_amount_payable')->nullable();
            $table->text('final_cash_cheque_no')->nullable();
            $table->string('final_cash_cheque_on')->nullable();
            $table->text('final_bank')->nullable();
            $table->text('final_rupees_only')->nullable();

            // Final authorization
            $table->text('final_passed_by')->nullable();
            $table->text('final_certified_by')->nullable();
            $table->text('final_prepared_by')->nullable();
            $table->text('final_payment_received_by')->nullable();

            // Bilties
            $table->text('received_no_bilties')->nullable();

            // Audit
            $table->timestamps();
            $table->datetime('date_created')->nullable();
            $table->datetime('date_modified')->nullable();
            $table->json('modified_dates')->nullable();

            // No DB-level foreign key constraints — per codebase convention
            // relationships are handled in app code. The indexed columns above
            // are sufficient for query performance.
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('lorry_receipts');
    }
};
