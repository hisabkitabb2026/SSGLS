<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds all transport-specific fields as native columns on the `invoices` table.
 *
 * Previously these fields were stored as custom field values (polymorphic
 * custom_field_values table), which made querying slow and complex â€”
 * especially for ProfitLossCalculationService which expected native columns.
 *
 * These columns are nullable because they are only populated for specific
 * template_name values (lr_receipt, lorry_receipt, office_invoice).
 * Standard invoices (invoice1, invoice2, â€¦) leave them null.
 *
 * NOTE: Most fields use TEXT type instead of VARCHAR(255) to avoid MySQL's
 * 65535-byte row size limit. TEXT columns store data off-page and don't
 * count toward the row size.
 *
 * NOTE: This migration is idempotent â€” it checks if each column already
 * exists before adding it, because a prior partial run may have added some
 * columns before failing on row size limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // â”€â”€ LR Receipt: Trip Details â”€â”€
            if (! Schema::hasColumn('invoices', 'from_code')) {
                $table->text('from_code')->nullable()->after('office_invoice_number');
            }
            if (! Schema::hasColumn('invoices', 'from_name')) {
                $table->text('from_name')->nullable()->after('from_code');
            }
            if (! Schema::hasColumn('invoices', 'to_code')) {
                $table->text('to_code')->nullable()->after('from_name');
            }
            if (! Schema::hasColumn('invoices', 'to_name')) {
                $table->text('to_name')->nullable()->after('to_code');
            }
            if (! Schema::hasColumn('invoices', 'truck_no')) {
                $table->text('truck_no')->nullable()->after('to_name');
            }
            if (! Schema::hasColumn('invoices', 'mode_of_payment')) {
                $table->text('mode_of_payment')->nullable()->after('truck_no');
            }
            if (! Schema::hasColumn('invoices', 'gst_tax_payable_by')) {
                $table->text('gst_tax_payable_by')->nullable()->after('mode_of_payment');
            }

            // â”€â”€ LR Receipt: Consignment Details â”€â”€
            if (! Schema::hasColumn('invoices', 'description_of_goods')) {
                $table->text('description_of_goods')->nullable()->after('gst_tax_payable_by');
            }
            if (! Schema::hasColumn('invoices', 'hsn_code')) {
                $table->text('hsn_code')->nullable()->after('description_of_goods');
            }
            if (! Schema::hasColumn('invoices', 'eway_bill_no')) {
                $table->text('eway_bill_no')->nullable()->after('hsn_code');
            }
            if (! Schema::hasColumn('invoices', 'actual_weight')) {
                $table->text('actual_weight')->nullable()->after('eway_bill_no');
            }
            if (! Schema::hasColumn('invoices', 'charged_weight')) {
                $table->text('charged_weight')->nullable()->after('actual_weight');
            }
            if (! Schema::hasColumn('invoices', 'no_of_articles')) {
                $table->text('no_of_articles')->nullable()->after('charged_weight');
            }
            if (! Schema::hasColumn('invoices', 'packing')) {
                $table->text('packing')->nullable()->after('no_of_articles');
            }

            // â”€â”€ LR Receipt: Freight Details (integer cents) â”€â”€
            if (! Schema::hasColumn('invoices', 'basic_freight')) {
                $table->bigInteger('basic_freight')->nullable()->after('packing');
            }
            if (! Schema::hasColumn('invoices', 'hamali')) {
                $table->bigInteger('hamali')->nullable()->after('basic_freight');
            }
            if (! Schema::hasColumn('invoices', 'fov')) {
                $table->bigInteger('fov')->nullable()->after('hamali');
            }
            if (! Schema::hasColumn('invoices', 'local_collection')) {
                $table->bigInteger('local_collection')->nullable()->after('fov');
            }
            if (! Schema::hasColumn('invoices', 'door_delivery')) {
                $table->bigInteger('door_delivery')->nullable()->after('local_collection');
            }
            if (! Schema::hasColumn('invoices', 'docket_charge')) {
                $table->bigInteger('docket_charge')->nullable()->after('door_delivery');
            }
            if (! Schema::hasColumn('invoices', 'other_charge')) {
                $table->bigInteger('other_charge')->nullable()->after('docket_charge');
            }
            if (! Schema::hasColumn('invoices', 'net_amount')) {
                $table->bigInteger('net_amount')->nullable()->after('other_charge');
            }

            // â”€â”€ Lorry Receipt: Trip Details â”€â”€
            if (! Schema::hasColumn('invoices', 'no_of_pages')) {
                $table->text('no_of_pages')->nullable()->after('net_amount');
            }
            if (! Schema::hasColumn('invoices', 'no_of_packages')) {
                $table->text('no_of_packages')->nullable()->after('no_of_pages');
            }
            if (! Schema::hasColumn('invoices', 'distance_kms')) {
                $table->text('distance_kms')->nullable()->after('no_of_packages');
            }
            if (! Schema::hasColumn('invoices', 'rate')) {
                $table->text('rate')->nullable()->after('distance_kms');
            }

            // â”€â”€ Lorry Receipt: Registration â”€â”€
            if (! Schema::hasColumn('invoices', 'regd_at')) {
                $table->text('regd_at')->nullable()->after('rate');
            }

            // â”€â”€ Lorry Receipt: Vehicle Details â”€â”€
            if (! Schema::hasColumn('invoices', 'body_type')) {
                $table->text('body_type')->nullable()->after('regd_at');
            }
            if (! Schema::hasColumn('invoices', 'make')) {
                $table->text('make')->nullable()->after('body_type');
            }
            if (! Schema::hasColumn('invoices', 'vehicle_model')) {
                $table->text('vehicle_model')->nullable()->after('make');
            }
            if (! Schema::hasColumn('invoices', 'colour')) {
                $table->text('colour')->nullable()->after('vehicle_model');
            }
            if (! Schema::hasColumn('invoices', 'chasis_no')) {
                $table->text('chasis_no')->nullable()->after('colour');
            }
            if (! Schema::hasColumn('invoices', 'engine_no')) {
                $table->text('engine_no')->nullable()->after('chasis_no');
            }

            // â”€â”€ Lorry Receipt: Fitness & Permit â”€â”€
            if (! Schema::hasColumn('invoices', 'fitness_validity')) {
                $table->text('fitness_validity')->nullable()->after('engine_no');
            }
            if (! Schema::hasColumn('invoices', 'road_permit_no')) {
                $table->text('road_permit_no')->nullable()->after('fitness_validity');
            }
            if (! Schema::hasColumn('invoices', 'permit_date')) {
                $table->text('permit_date')->nullable()->after('road_permit_no');
            }
            if (! Schema::hasColumn('invoices', 'permit_valid_in')) {
                $table->text('permit_valid_in')->nullable()->after('permit_date');
            }
            if (! Schema::hasColumn('invoices', 'permit_status_upto')) {
                $table->text('permit_status_upto')->nullable()->after('permit_valid_in');
            }

            // â”€â”€ Lorry Receipt: Insurance â”€â”€
            if (! Schema::hasColumn('invoices', 'insured_with')) {
                $table->text('insured_with')->nullable()->after('permit_status_upto');
            }
            if (! Schema::hasColumn('invoices', 'insurance_division_no')) {
                $table->text('insurance_division_no')->nullable()->after('insured_with');
            }
            if (! Schema::hasColumn('invoices', 'insurance_certificate_no')) {
                $table->text('insurance_certificate_no')->nullable()->after('insurance_division_no');
            }
            if (! Schema::hasColumn('invoices', 'insurance_valid_upto')) {
                $table->text('insurance_valid_upto')->nullable()->after('insurance_certificate_no');
            }

            // â”€â”€ Lorry Receipt: Owner Details â”€â”€
            if (! Schema::hasColumn('invoices', 'owner_code')) {
                $table->text('owner_code')->nullable()->after('insurance_valid_upto');
            }
            if (! Schema::hasColumn('invoices', 'owner_name')) {
                $table->text('owner_name')->nullable()->after('owner_code');
            }
            if (! Schema::hasColumn('invoices', 'owner_address')) {
                $table->text('owner_address')->nullable()->after('owner_name');
            }
            if (! Schema::hasColumn('invoices', 'owner_phone')) {
                $table->text('owner_phone')->nullable()->after('owner_address');
            }
            if (! Schema::hasColumn('invoices', 'owner_bank_account_no')) {
                $table->text('owner_bank_account_no')->nullable()->after('owner_phone');
            }
            if (! Schema::hasColumn('invoices', 'owner_pan_no')) {
                $table->text('owner_pan_no')->nullable()->after('owner_bank_account_no');
            }

            // â”€â”€ Lorry Receipt: Financer â”€â”€
            if (! Schema::hasColumn('invoices', 'financer_name')) {
                $table->text('financer_name')->nullable()->after('owner_pan_no');
            }
            if (! Schema::hasColumn('invoices', 'financer_address')) {
                $table->text('financer_address')->nullable()->after('financer_name');
            }

            // â”€â”€ Lorry Receipt: Driver Details â”€â”€
            if (! Schema::hasColumn('invoices', 'driver_name')) {
                $table->text('driver_name')->nullable()->after('financer_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_address')) {
                $table->text('driver_address')->nullable()->after('driver_name');
            }
            if (! Schema::hasColumn('invoices', 'driver_place')) {
                $table->text('driver_place')->nullable()->after('driver_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_no')) {
                $table->text('driver_licence_no')->nullable()->after('driver_place');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_date')) {
                $table->text('driver_licence_date')->nullable()->after('driver_licence_no');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_issued_by')) {
                $table->text('driver_licence_issued_by')->nullable()->after('driver_licence_date');
            }
            if (! Schema::hasColumn('invoices', 'driver_rto_address')) {
                $table->text('driver_rto_address')->nullable()->after('driver_licence_issued_by');
            }
            if (! Schema::hasColumn('invoices', 'driver_valid_up_to')) {
                $table->text('driver_valid_up_to')->nullable()->after('driver_rto_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_bank_account_no')) {
                $table->text('driver_bank_account_no')->nullable()->after('driver_valid_up_to');
            }

            // â”€â”€ Lorry Receipt: Broker Details â”€â”€
            if (! Schema::hasColumn('invoices', 'broker_name')) {
                $table->text('broker_name')->nullable()->after('driver_bank_account_no');
            }
            if (! Schema::hasColumn('invoices', 'broker_address')) {
                $table->text('broker_address')->nullable()->after('broker_name');
            }
            if (! Schema::hasColumn('invoices', 'broker_pan_no')) {
                $table->text('broker_pan_no')->nullable()->after('broker_address');
            }
            if (! Schema::hasColumn('invoices', 'broker_phone_no')) {
                $table->text('broker_phone_no')->nullable()->after('broker_pan_no');
            }
            if (! Schema::hasColumn('invoices', 'broker_bank_account_no')) {
                $table->text('broker_bank_account_no')->nullable()->after('broker_phone_no');
            }

            // â”€â”€ Lorry Receipt: Advice â”€â”€
            if (! Schema::hasColumn('invoices', 'advice_no')) {
                $table->text('advice_no')->nullable()->after('broker_bank_account_no');
            }
            if (! Schema::hasColumn('invoices', 'advice_date')) {
                $table->text('advice_date')->nullable()->after('advice_no');
            }

            // â”€â”€ Lorry Receipt: Destination Broker â”€â”€
            if (! Schema::hasColumn('invoices', 'destination_broker_name')) {
                $table->text('destination_broker_name')->nullable()->after('advice_date');
            }
            if (! Schema::hasColumn('invoices', 'destination_broker_address')) {
                $table->text('destination_broker_address')->nullable()->after('destination_broker_name');
            }

            // â”€â”€ Lorry Receipt: Hire & Payment â€” Initial â”€â”€
            if (! Schema::hasColumn('invoices', 'paid_to')) {
                $table->text('paid_to')->nullable()->after('destination_broker_address');
            }
            if (! Schema::hasColumn('invoices', 'lorry_hire_amount')) {
                $table->text('lorry_hire_amount')->nullable()->after('paid_to');
            }
            if (! Schema::hasColumn('invoices', 'other_charges_amount')) {
                $table->text('other_charges_amount')->nullable()->after('lorry_hire_amount');
            }
            if (! Schema::hasColumn('invoices', 'gross_hire_rupees')) {
                $table->text('gross_hire_rupees')->nullable()->after('other_charges_amount');
            }
            if (! Schema::hasColumn('invoices', 'gross_hire_amount')) {
                $table->text('gross_hire_amount')->nullable()->after('gross_hire_rupees');
            }

            // â”€â”€ Lorry Receipt: Advance â”€â”€
            if (! Schema::hasColumn('invoices', 'advance_cash_cheque_no')) {
                $table->text('advance_cash_cheque_no')->nullable()->after('gross_hire_amount');
            }
            if (! Schema::hasColumn('invoices', 'advance_on')) {
                $table->text('advance_on')->nullable()->after('advance_cash_cheque_no');
            }
            if (! Schema::hasColumn('invoices', 'advance_bank')) {
                $table->text('advance_bank')->nullable()->after('advance_on');
            }
            if (! Schema::hasColumn('invoices', 'advance_amount')) {
                $table->text('advance_amount')->nullable()->after('advance_bank');
            }

            // â”€â”€ Lorry Receipt: Balance â”€â”€
            if (! Schema::hasColumn('invoices', 'balance_payable_at')) {
                $table->text('balance_payable_at')->nullable()->after('advance_amount');
            }
            if (! Schema::hasColumn('invoices', 'balance_payable_code')) {
                $table->text('balance_payable_code')->nullable()->after('balance_payable_at');
            }
            if (! Schema::hasColumn('invoices', 'balance_rupees')) {
                $table->text('balance_rupees')->nullable()->after('balance_payable_code');
            }
            if (! Schema::hasColumn('invoices', 'balance_amount')) {
                $table->text('balance_amount')->nullable()->after('balance_rupees');
            }
            if (! Schema::hasColumn('invoices', 'balance_rupees_only')) {
                $table->text('balance_rupees_only')->nullable()->after('balance_amount');
            }

            // â”€â”€ Lorry Receipt: Authorization â”€â”€
            if (! Schema::hasColumn('invoices', 'hire_passed_by')) {
                $table->text('hire_passed_by')->nullable()->after('balance_rupees_only');
            }
            if (! Schema::hasColumn('invoices', 'hire_certified_by')) {
                $table->text('hire_certified_by')->nullable()->after('hire_passed_by');
            }
            if (! Schema::hasColumn('invoices', 'hire_prepared_by')) {
                $table->text('hire_prepared_by')->nullable()->after('hire_certified_by');
            }
            if (! Schema::hasColumn('invoices', 'advance_received_by')) {
                $table->text('advance_received_by')->nullable()->after('hire_prepared_by');
            }

            // â”€â”€ Lorry Receipt: Loading â”€â”€
            if (! Schema::hasColumn('invoices', 'loading_remarks')) {
                $table->text('loading_remarks')->nullable()->after('advance_received_by');
            }
            if (! Schema::hasColumn('invoices', 'loaded_by')) {
                $table->text('loaded_by')->nullable()->after('loading_remarks');
            }

            // â”€â”€ Lorry Receipt: Final Settlement â”€â”€
            if (! Schema::hasColumn('invoices', 'final_paid_to')) {
                $table->text('final_paid_to')->nullable()->after('loaded_by');
            }
            if (! Schema::hasColumn('invoices', 'detention_amount')) {
                $table->text('detention_amount')->nullable()->after('final_paid_to');
            }
            if (! Schema::hasColumn('invoices', 'extra_hire_amount')) {
                $table->text('extra_hire_amount')->nullable()->after('detention_amount');
            }
            if (! Schema::hasColumn('invoices', 'final_other_amount')) {
                $table->text('final_other_amount')->nullable()->after('extra_hire_amount');
            }
            if (! Schema::hasColumn('invoices', 'final_total_extra_amount')) {
                $table->text('final_total_extra_amount')->nullable()->after('final_other_amount');
            }
            if (! Schema::hasColumn('invoices', 'grand_total_amount')) {
                $table->text('grand_total_amount')->nullable()->after('final_total_extra_amount');
            }
            if (! Schema::hasColumn('invoices', 'less_advance_other_branch_amount')) {
                $table->text('less_advance_other_branch_amount')->nullable()->after('grand_total_amount');
            }
            if (! Schema::hasColumn('invoices', 'less_deduction_claims_amount')) {
                $table->text('less_deduction_claims_amount')->nullable()->after('less_advance_other_branch_amount');
            }
            if (! Schema::hasColumn('invoices', 'total_less_amount')) {
                $table->text('total_less_amount')->nullable()->after('less_deduction_claims_amount');
            }

            // â”€â”€ Lorry Receipt: Final Balance â”€â”€
            if (! Schema::hasColumn('invoices', 'final_balance_paid_at')) {
                $table->text('final_balance_paid_at')->nullable()->after('total_less_amount');
            }
            if (! Schema::hasColumn('invoices', 'final_balance_code')) {
                $table->text('final_balance_code')->nullable()->after('final_balance_paid_at');
            }
            if (! Schema::hasColumn('invoices', 'final_balance_on')) {
                $table->text('final_balance_on')->nullable()->after('final_balance_code');
            }
            if (! Schema::hasColumn('invoices', 'net_amount_payable')) {
                $table->text('net_amount_payable')->nullable()->after('final_balance_on');
            }
            if (! Schema::hasColumn('invoices', 'final_cash_cheque_no')) {
                $table->text('final_cash_cheque_no')->nullable()->after('net_amount_payable');
            }
            if (! Schema::hasColumn('invoices', 'final_cash_cheque_on')) {
                $table->text('final_cash_cheque_on')->nullable()->after('final_cash_cheque_no');
            }
            if (! Schema::hasColumn('invoices', 'final_bank')) {
                $table->text('final_bank')->nullable()->after('final_cash_cheque_on');
            }
            if (! Schema::hasColumn('invoices', 'final_rupees_only')) {
                $table->text('final_rupees_only')->nullable()->after('final_bank');
            }

            // â”€â”€ Lorry Receipt: Final Authorization â”€â”€
            if (! Schema::hasColumn('invoices', 'final_passed_by')) {
                $table->text('final_passed_by')->nullable()->after('final_rupees_only');
            }
            if (! Schema::hasColumn('invoices', 'final_certified_by')) {
                $table->text('final_certified_by')->nullable()->after('final_passed_by');
            }
            if (! Schema::hasColumn('invoices', 'final_prepared_by')) {
                $table->text('final_prepared_by')->nullable()->after('final_certified_by');
            }
            if (! Schema::hasColumn('invoices', 'final_payment_received_by')) {
                $table->text('final_payment_received_by')->nullable()->after('final_prepared_by');
            }

            // â”€â”€ Lorry Receipt: Bilties & Contract â”€â”€
            if (! Schema::hasColumn('invoices', 'received_no_bilties')) {
                $table->text('received_no_bilties')->nullable()->after('final_payment_received_by');
            }
            if (! Schema::hasColumn('invoices', 'contract_no')) {
                $table->text('contract_no')->nullable()->after('received_no_bilties');
            }

            // â”€â”€ Lorry Receipt: Party References (customers.id is BIGINT UNSIGNED) â”€â”€
            if (! Schema::hasColumn('invoices', 'owner_customer_id')) {
                $table->unsignedBigInteger('owner_customer_id')->nullable()->index()->after('contract_no');
            }
            if (! Schema::hasColumn('invoices', 'driver_customer_id')) {
                $table->unsignedBigInteger('driver_customer_id')->nullable()->index()->after('owner_customer_id');
            }
            if (! Schema::hasColumn('invoices', 'broker_customer_id')) {
                $table->unsignedBigInteger('broker_customer_id')->nullable()->index()->after('driver_customer_id');
            }
        });
    }

    public function down(): void
    {
        $allColumns = [
            'from_code', 'from_name', 'to_code', 'to_name', 'truck_no',
            'mode_of_payment', 'gst_tax_payable_by',
            'description_of_goods', 'hsn_code', 'eway_bill_no', 'actual_weight',
            'charged_weight', 'no_of_articles', 'packing',
            'basic_freight', 'hamali', 'fov', 'local_collection', 'door_delivery',
            'docket_charge', 'other_charge', 'net_amount',
            'no_of_pages', 'no_of_packages', 'distance_kms', 'rate',
            'regd_at',
            'body_type', 'make', 'vehicle_model', 'colour', 'chasis_no', 'engine_no',
            'fitness_validity', 'road_permit_no', 'permit_date',
            'permit_valid_in', 'permit_status_upto',
            'insured_with', 'insurance_division_no', 'insurance_certificate_no',
            'insurance_valid_upto',
            'owner_code', 'owner_name', 'owner_address', 'owner_phone',
            'owner_bank_account_no', 'owner_pan_no',
            'financer_name', 'financer_address',
            'driver_name', 'driver_address', 'driver_place', 'driver_licence_no',
            'driver_licence_date', 'driver_licence_issued_by', 'driver_rto_address',
            'driver_valid_up_to', 'driver_bank_account_no',
            'broker_name', 'broker_address', 'broker_pan_no', 'broker_phone_no',
            'broker_bank_account_no',
            'advice_no', 'advice_date',
            'destination_broker_name', 'destination_broker_address',
            'paid_to', 'lorry_hire_amount', 'other_charges_amount',
            'gross_hire_rupees', 'gross_hire_amount',
            'advance_cash_cheque_no', 'advance_on', 'advance_bank', 'advance_amount',
            'balance_payable_at', 'balance_payable_code', 'balance_rupees',
            'balance_amount', 'balance_rupees_only',
            'hire_passed_by', 'hire_certified_by', 'hire_prepared_by',
            'advance_received_by',
            'loading_remarks', 'loaded_by',
            'final_paid_to', 'detention_amount', 'extra_hire_amount',
            'final_other_amount', 'final_total_extra_amount', 'grand_total_amount',
            'less_advance_other_branch_amount', 'less_deduction_claims_amount',
            'total_less_amount',
            'final_balance_paid_at', 'final_balance_code', 'final_balance_on',
            'net_amount_payable', 'final_cash_cheque_no', 'final_cash_cheque_on',
            'final_bank', 'final_rupees_only',
            'final_passed_by', 'final_certified_by', 'final_prepared_by',
            'final_payment_received_by',
            'received_no_bilties', 'contract_no',
            'owner_customer_id', 'driver_customer_id', 'broker_customer_id',
        ];

        // Drop indexes first
        foreach (['owner_customer_id', 'driver_customer_id', 'broker_customer_id'] as $col) {
            if (Schema::hasColumn('invoices', $col)) {
                // Index name follows Laravel convention: invoices_{col}_index
                try {
                    Schema::table('invoices', function (Blueprint $table) use ($col) {
                        $table->dropIndex("invoices_{$col}_index");
                    });
                } catch (Exception $e) {
                    // Index may not exist â€” ignore
                }
            }
        }

        // Drop columns that exist
        $existing = array_filter($allColumns, fn ($col) => Schema::hasColumn('invoices', $col));
        if (! empty($existing)) {
            Schema::table('invoices', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
