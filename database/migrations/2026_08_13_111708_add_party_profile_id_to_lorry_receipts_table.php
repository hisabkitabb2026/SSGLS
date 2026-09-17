<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add party_profile_id column to lorry_receipts table.
 *
 * This column references lorry_party_profiles.id and replaces the
 * Customer-based "Party" selector on the Lorry Receipt form.
 * The Party field now uses LorryPartyProfile (OWNER/BROKER types)
 * instead of the Customer module.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lorry_receipts', function (Blueprint $table) {
            // LorryPartyProfile FK (INT UNSIGNED — matches lorry_party_profiles.id)
            $table->unsignedBigInteger('party_profile_id')->nullable()->index()->after('broker_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lorry_receipts', function (Blueprint $table) {
            $table->dropColumn('party_profile_id');
        });
    }
};
