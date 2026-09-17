<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lorry_party_profiles', function (Blueprint $table) {
            $table->string('alternate_phone')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('gstin')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_holder_name')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lorry_party_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'alternate_phone',
                'pan_number',
                'gstin',
                'bank_name',
                'bank_account_holder_name',
                'ifsc_code',
                'upi_id',
                'status',
                'notes',
            ]);
        });
    }
};
