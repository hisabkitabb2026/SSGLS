<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add party_profile_id column to invoices table.
 *
 * Lorry Receipts (template_name = 'lorry_receipt') use a LorryPartyProfile
 * as the "Party" field instead of a Customer.  The party_profile_id was
 * previously only on the lorry_receipts table, but Lorry Receipts are now
 * stored as Invoice records with template_name = 'lorry_receipt'.  This
 * migration adds the column to the invoices table so the Party selector
 * can hydrate correctly when editing an existing Lorry Receipt.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'party_profile_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                // LorryPartyProfile FK (INT UNSIGNED — matches lorry_party_profiles.id)
                $table->unsignedBigInteger('party_profile_id')->nullable()->index()->after('broker_customer_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'party_profile_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('party_profile_id');
            });
        }
    }
};
