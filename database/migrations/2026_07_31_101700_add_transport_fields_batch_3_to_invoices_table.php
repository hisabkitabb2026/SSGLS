<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
Batch 3: Lorry Receipt Broker, Advance, Balance, Authorization, Final Settlement (25 columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ── Lorry Receipt: Broker Details ──
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

            // ── Lorry Receipt: Advice ──
            if (! Schema::hasColumn('invoices', 'advice_no')) {
                $table->text('advice_no')->nullable()->after('broker_bank_account_no');
            }
            if (! Schema::hasColumn('invoices', 'advice_date')) {
                $table->text('advice_date')->nullable()->after('advice_no');
            }

            // ── Lorry Receipt: Destination Broker ──
            if (! Schema::hasColumn('invoices', 'destination_broker_name')) {
                $table->text('destination_broker_name')->nullable()->after('advice_date');
            }
            if (! Schema::hasColumn('invoices', 'destination_broker_address')) {
                $table->text('destination_broker_address')->nullable()->after('destination_broker_name');
            }

            // ── Lorry Receipt: Hire & Payment — Initial ──
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

            // ── Lorry Receipt: Advance ──
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

            // ── Lorry Receipt: Balance ──
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

            // ── Lorry Receipt: Authorization ──
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

            // ── Lorry Receipt: Loading ──
            if (! Schema::hasColumn('invoices', 'loading_remarks')) {
                $table->text('loading_remarks')->nullable()->after('advance_received_by');
            }
            if (! Schema::hasColumn('invoices', 'loaded_by')) {
                $table->text('loaded_by')->nullable()->after('loading_remarks');
            }
        });
    }

    public function down(): void
    {
        $columns = [
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
        ];

        $existing = array_filter($columns, fn ($col) => Schema::hasColumn('invoices', $col));
        if (! empty($existing)) {
            Schema::table('invoices', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
