<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `rate_card` JSON column to the items and estimate_items tables.
 *
 * In the logistics quotation domain, each Item (station) carries a rate card
 * mapping weight-type (unit_id) → rate (in cents).  This JSON column replaces
 * the old approach of creating one Item row per station+weight combination.
 *
 * The same column is added to estimate_items so that each estimate line item
 * stores a frozen copy of the rate card at quotation time — if the master Item's
 * rates change later, existing estimates keep their original rates.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->json('rate_card')->nullable()->after('truck_type');
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->json('rate_card')->nullable()->after('unit_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('rate_card');
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn('rate_card');
        });
    }
};
