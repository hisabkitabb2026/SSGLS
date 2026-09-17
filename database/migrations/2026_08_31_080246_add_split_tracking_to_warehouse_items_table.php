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
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->unsignedInteger('split_from_warehouse_item_id')->nullable();
            $table->index('split_from_warehouse_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropIndex(['split_from_warehouse_item_id']);
            $table->dropColumn('split_from_warehouse_item_id');
        });
    }
};
