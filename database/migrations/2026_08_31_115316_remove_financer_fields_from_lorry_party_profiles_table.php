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
            $table->dropColumn(['financer_name', 'financer_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lorry_party_profiles', function (Blueprint $table) {
            $table->string('financer_name')->nullable();
            $table->text('financer_address')->nullable();
        });
    }
};
