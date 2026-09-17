<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds columns for Profit & Loss report calculations directly on the invoices table.
     * These columns store pre-calculated income (amount_credit) and expense (amount_debit)
     * values for LR Receipts, matching the ssgls implementation.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Amount credit (income) - stored in cents like the total column
            $table->bigInteger('amount_credit')->nullable()->after('total')->comment('Income amount for P&L report (in cents)');

            // Amount debit (expense) - stored in cents like the total column (NOT rupees -保持一致)
            $table->bigInteger('amount_debit')->nullable()->after('amount_credit')->comment('Expense amount for P&L report (in cents)');

            // Date when credit was received
            $table->date('amount_credit_date')->nullable()->after('amount_debit');

            // Date when debit was paid
            $table->date('amount_debit_date')->nullable()->after('amount_credit_date');

            // Linked office invoice number
            $table->string('office_invoice_number')->nullable()->after('amount_debit_date');

            // Linked challan number (from lorry receipt)
            $table->string('challan_number')->nullable()->after('office_invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'amount_credit',
                'amount_debit',
                'amount_credit_date',
                'amount_debit_date',
                'office_invoice_number',
                'challan_number',
            ]);
        });
    }
};
