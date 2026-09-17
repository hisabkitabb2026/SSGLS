<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds logistics-critical fields to warehouse_items:
 *
 * - load_type: distinguishes Full Load (direct dispatch) from Part Load (warehouse + consolidation)
 * - promised_dispatch_date: the deadline the transporter gave the customer for dispatch
 * - weight_kg, no_of_packages, goods_description: denormalized from LR for fast list rendering
 * - consignor_name, consignee_name: denormalized for display without joining invoices
 * - priority: operational urgency flag
 *
 * These fields enable the Part-Load consolidation workflow:
 * aging tracking, deadline alerts, and fill-percentage calculations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouse_items', 'load_type')) {
                $table->enum('load_type', ['full_load', 'part_load'])
                    ->default('part_load')
                    ->after('status');
            }

            if (! Schema::hasColumn('warehouse_items', 'promised_dispatch_date')) {
                $table->date('promised_dispatch_date')->nullable()->after('date_received');
                $table->index('promised_dispatch_date');
            }

            if (! Schema::hasColumn('warehouse_items', 'weight_kg')) {
                $table->decimal('weight_kg', 10, 2)->default(0)->after('destination_city');
            }

            if (! Schema::hasColumn('warehouse_items', 'no_of_packages')) {
                $table->integer('no_of_packages')->default(0)->after('weight_kg');
            }

            if (! Schema::hasColumn('warehouse_items', 'goods_description')) {
                $table->text('goods_description')->nullable()->after('no_of_packages');
            }

            if (! Schema::hasColumn('warehouse_items', 'consignor_name')) {
                $table->string('consignor_name')->nullable()->after('goods_description');
            }

            if (! Schema::hasColumn('warehouse_items', 'consignee_name')) {
                $table->string('consignee_name')->nullable()->after('consignor_name');
            }

            if (! Schema::hasColumn('warehouse_items', 'priority')) {
                $table->enum('priority', ['normal', 'urgent', 'critical'])
                    ->default('normal')
                    ->after('consignee_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $columns = [
                'load_type',
                'promised_dispatch_date',
                'weight_kg',
                'no_of_packages',
                'goods_description',
                'consignor_name',
                'consignee_name',
                'priority',
            ];

            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('warehouse_items', $col));

            if (! empty($existing)) {
                // Drop index on promised_dispatch_date if it exists
                if (in_array('promised_dispatch_date', $existing)) {
                    try {
                        $table->dropIndex(['promised_dispatch_date']);
                    } catch (Exception $e) {
                        // Index may not exist — ignore
                    }
                }
                $table->dropColumn($existing);
            }
        });
    }
};
