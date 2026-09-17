<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `truck_type` column to the items table.
 *
 * In the logistics quotation domain each Item (station) can have a truck type
 * (e.g. Open, Container, Tanker) that — together with the station name and
 * truck weight (unit) — determines the rate.  This column stores that type
 * so the ItemModal can capture all four pieces of pricing data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('truck_type')->nullable()->after('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('truck_type');
        });
    }
};
