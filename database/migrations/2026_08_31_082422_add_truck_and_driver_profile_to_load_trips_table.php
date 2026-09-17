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
        Schema::table('load_trips', function (Blueprint $table) {
            $table->unsignedInteger('truck_id')->nullable();
            $table->unsignedInteger('driver_profile_id')->nullable();
            $table->index('truck_id');
            $table->index('driver_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('load_trips', function (Blueprint $table) {
            $table->dropIndex(['truck_id']);
            $table->dropIndex(['driver_profile_id']);
            $table->dropColumn(['truck_id', 'driver_profile_id']);
        });
    }
};
