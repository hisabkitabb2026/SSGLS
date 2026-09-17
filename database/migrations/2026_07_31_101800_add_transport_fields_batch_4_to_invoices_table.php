<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
Batch 4: Lorry Receipt Final Settlement & Foreign Keys (19 columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ── Lorry Receipt: Final Settlement ──
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

            // ── Lorry Receipt: Final Balance ──
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

            // ── Lorry Receipt: Final Authorization ──
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

            // ── Lorry Receipt: Bilties & Contract ──
            if (! Schema::hasColumn('invoices', 'received_no_bilties')) {
                $table->text('received_no_bilties')->nullable()->after('final_payment_received_by');
            }
            if (! Schema::hasColumn('invoices', 'contract_no')) {
                $table->text('contract_no')->nullable()->after('received_no_bilties');
            }

            // ── Lorry Receipt: Party References (customers.id is BIGINT UNSIGNED) ──
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
        // Drop indexes first
        foreach (['owner_customer_id', 'driver_customer_id', 'broker_customer_id'] as $col) {
            if (Schema::hasColumn('invoices', $col)) {
                try {
                    Schema::table('invoices', function (Blueprint $table) use ($col) {
                        $table->dropIndex("invoices_{$col}_index");
                    });
                } catch (Exception $e) {
                    // Index may not exist — ignore
                }
            }
        }

        $columns = [
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

        $existing = array_filter($columns, fn ($col) => Schema::hasColumn('invoices', $col));
        if (! empty($existing)) {
            Schema::table('invoices', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
