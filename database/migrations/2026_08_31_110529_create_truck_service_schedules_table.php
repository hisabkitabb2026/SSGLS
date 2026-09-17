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
        Schema::create('truck_service_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('truck_id')->index();
            $table->string('name');
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->unsignedInteger('last_service_odometer_km')->nullable();
            $table->date('last_service_date')->nullable();
            $table->unsignedInteger('next_due_odometer_km')->nullable();
            $table->date('next_due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truck_service_schedules');
    }
};
