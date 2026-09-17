<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds GSTIN and PAN No fields to companies table.
     * The enrollment_no column already exists from the
     * 2026_06_28_160000_add_transport_fields_to_invoices_and_companies migration.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('gstin')->nullable()->after('enrollment_no');
            $table->string('pan_no')->nullable()->after('gstin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'pan_no']);
        });
    }
};
