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
        Schema::table('estimates', function (Blueprint $table) {
            $table->string('estimate_type')->default('estimate')->after('status');
            $table->index('estimate_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estimates', function (Blueprint $table) {
            $table->dropIndex(['estimate_type']);
            $table->dropColumn('estimate_type');
        });
    }
};
