<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Load Trips (Truck Dispatches)
 *
 * A load trip is the actual truck dispatch that carries a consolidation group
 * (or a single Full-Load item) from the warehouse to the destination.
 *
 * Lifecycle: planned → dispatched → delivered | cancelled
 *
 * The warehouse_items.delivery_id FK (already exists in the table) links
 * items to their load trip for status tracking.
 *
 * Future scaling: support multi-leg trips, GPS tracking integration,
 * Fastag/E-Way Bill API links — see TODO comments below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_trips', function (Blueprint $table) {
            $table->id();

            // Company / Transporter
            $table->unsignedInteger('company_id');
            $table->index('company_id');

            // Link to consolidation group (nullable for full-load direct dispatches)
            $table->unsignedBigInteger('consolidation_group_id')->nullable();
            $table->index('consolidation_group_id');

            // Human-readable trip number
            $table->string('trip_number');

            // Vehicle & Driver details
            $table->string('truck_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();

            // Route
            $table->string('origin_city')->nullable();
            $table->string('destination_city');

            // Schedule
            $table->timestamp('dispatch_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            // Lifecycle status
            $table->enum('status', [
                'planned',    // truck assigned, not yet departed
                'dispatched', // on the road
                'delivered',  // reached destination
                'cancelled',
            ])->default('planned');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['company_id', 'trip_number']);
            $table->index(['company_id', 'status']);
            $table->index(['destination_city', 'status']);
            $table->index(['dispatch_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_trips');
    }
};
