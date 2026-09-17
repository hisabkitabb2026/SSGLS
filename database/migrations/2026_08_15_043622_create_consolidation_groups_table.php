<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidation Groups
 *
 * A consolidation group is a batch of Part-Load warehouse items destined for the
 * same route/destination, accumulated in the warehouse until enough weight is
 * gathered to fill a truck. Once the fill threshold is reached, the group is
 * marked "ready" and a Load Trip (truck dispatch) is created.
 *
 * Lifecycle: open → ready → dispatched → completed | cancelled
 *
 * The warehouse_items.consolidation_id FK (already exists in the table) links
 * items to their consolidation group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consolidation_groups', function (Blueprint $table) {
            $table->id();

            // Company / Transporter (INT UNSIGNED — matches companies.id)
            $table->unsignedInteger('company_id');
            $table->index('company_id');

            // Human-readable group number, unique per company
            $table->string('group_number');

            // Destination route — all items in a group share the same destination
            $table->string('destination_city');

            // Lifecycle status
            $table->enum('status', [
                'open',       // accumulating items
                'ready',      // enough weight, ready to dispatch
                'dispatched', // truck assigned, on the road
                'completed',  // delivered & confirmed
                'cancelled',
            ])->default('open');

            // Aggregated metrics (denormalized for fast board rendering)
            $table->decimal('total_weight_kg', 12, 2)->default(0);
            $table->integer('total_packages')->default(0);
            $table->integer('total_items')->default(0);

            // Target truck capacity for fill-percentage calculation
            $table->decimal('truck_capacity_kg', 12, 2)->default(9000);

            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique group number per company
            $table->unique(['company_id', 'group_number']);
            $table->index(['company_id', 'status']);
            $table->index(['destination_city', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consolidation_groups');
    }
};
