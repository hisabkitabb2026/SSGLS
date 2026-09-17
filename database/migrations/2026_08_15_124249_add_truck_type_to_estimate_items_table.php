<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `truck_type` column to the estimate_items table.
     *
     * This mirrors the column already present on the `items` table so that
     * estimate line items can carry the truck type selected from the item
     * master (e.g. "Open Body", "Container", "Tanker").  The EstimateItemResource
     * already returns this field; without the column the value is always null
     * on edit.
     */
    public function up(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->string('truck_type')->nullable()->after('unit_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn('truck_type');
        });
    }
};
