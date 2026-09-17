<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing Office Invoice item-level columns: pkg, weight, party_inv_no.
 *
 * The OfficeInvoiceItemsTable.vue form sends these fields, but the
 * invoice_items table doesn't have corresponding columns — so they were
 * silently dropped. This migration adds them so the data is persisted.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'pkg')) {
                $table->string('pkg')->nullable()->after('amount');
            }
            if (! Schema::hasColumn('invoice_items', 'weight')) {
                $table->string('weight')->nullable()->after('pkg');
            }
            if (! Schema::hasColumn('invoice_items', 'party_inv_no')) {
                $table->string('party_inv_no')->nullable()->after('weight');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['pkg', 'weight', 'party_inv_no']);
        });
    }
};
