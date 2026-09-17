<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Office Invoice item-level transport fields as native columns on the
 * `invoice_items` table.
 *
 * Previously these were stored as custom field values on the InvoiceItem
 * polymorphic relation. The critical field â€” consignment_number â€” is used by
 * ProfitLossCalculationService to link Office Invoice items to LR Receipts,
 * and was queried as a native column that didn't exist, causing a 500 error.
 *
 * All columns are nullable because standard invoices and other templates
 * don't use them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            // Office Invoice item-level fields
            $table->string('consignment_number')->nullable()->index()->after('unit_name');
            $table->date('consignment_date')->nullable()->after('consignment_number');
            $table->string('from_code')->nullable()->after('consignment_date');
            $table->string('from_name')->nullable()->after('from_code');
            $table->string('to_code')->nullable()->after('from_name');
            $table->string('to_name')->nullable()->after('to_code');
            $table->string('truck_no')->nullable()->after('to_name');

            // Charge fields (integer cents â€” consistent with price/total)
            $table->bigInteger('rate')->nullable()->after('truck_no');
            $table->bigInteger('other_charge')->nullable()->after('rate');
            $table->bigInteger('lr_charge')->nullable()->after('other_charge');
            $table->bigInteger('dd_charge')->nullable()->after('lr_charge');
            $table->bigInteger('amount')->nullable()->after('dd_charge');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex(['consignment_number']);

            $table->dropColumn([
                'consignment_number',
                'consignment_date',
                'from_code',
                'from_name',
                'to_code',
                'to_name',
                'truck_no',
                'rate',
                'other_charge',
                'lr_charge',
                'dd_charge',
                'amount',
            ]);
        });
    }
};
