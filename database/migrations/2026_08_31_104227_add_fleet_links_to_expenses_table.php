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
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('truck_id')->nullable()->index();
            $table->unsignedInteger('load_trip_id')->nullable()->index();
            $table->unsignedInteger('driver_profile_id')->nullable()->index();
            $table->unsignedInteger('odometer_reading_km')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['truck_id', 'load_trip_id', 'driver_profile_id', 'odometer_reading_km']);
        });
    }
};
