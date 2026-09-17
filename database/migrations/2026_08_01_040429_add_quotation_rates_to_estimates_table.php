<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->json('quotation_rates')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropColumn('quotation_rates');
        });
    }
};
