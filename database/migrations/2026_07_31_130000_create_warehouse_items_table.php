<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();

            // Company/Transporter
            $table->unsignedInteger('company_id');
            $table->index('company_id');

            // Link to LR Receipt (Invoice with type 'lorry_receipt')
            $table->unsignedInteger('lr_id');
            $table->index('lr_id');

            // Warehouse Location Details
            $table->string('warehouse_location')->nullable(); // e.g., RACK-A-1, SHELF-B-2
            $table->string('section_name')->nullable(); // Optional description

            // Dates
            $table->timestamp('date_received')->nullable();

            // Destination (from LR, but editable)
            $table->string('destination_city')->nullable(); // DELHI, JAIPUR, etc.

            // Status tracking
            $table->enum('status', [
                'stored',
                'picked_for_consolidation',
                'loaded_on_vehicle',
                'in_transit',
                'delivered',
                'cancelled',
            ])->default('stored');

            // Link to consolidation
            $table->unsignedBigInteger('consolidation_id')->nullable();
            $table->index('consolidation_id');

            // Link to delivery
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->index('delivery_id');

            // Notes
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for common queries
            $table->index(['company_id', 'status']);
            $table->index(['destination_city', 'status']);
            $table->index(['warehouse_location', 'company_id']);
            $table->index(['date_received']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_items');
    }
};
