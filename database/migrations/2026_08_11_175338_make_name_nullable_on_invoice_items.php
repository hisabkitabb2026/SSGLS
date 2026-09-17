<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Office Invoice (office_invoice template) items do not have a "name"
     * field — their identifier is the consignment_number. The same applies
     * to lr_receipt and lorry_receipt templates. Making this column nullable
     * allows those transport items to be saved without a dummy name value.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('name')->nullable(false)->default('')->change();
        });
    }
};
