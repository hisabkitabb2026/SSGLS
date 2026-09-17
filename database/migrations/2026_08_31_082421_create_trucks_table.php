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
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('owner_profile_id')->nullable()->index();
            $table->string('truck_number');
            $table->string('vehicle_type')->nullable();
            $table->string('body_type')->nullable();
            $table->string('make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->decimal('capacity_kg', 12, 2);
            $table->string('chassis_number')->nullable();
            $table->string('engine_number')->nullable();
            $table->date('rc_expiry_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->date('fitness_expiry_date')->nullable();
            $table->date('permit_expiry_date')->nullable();
            $table->string('status')->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'truck_number']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
