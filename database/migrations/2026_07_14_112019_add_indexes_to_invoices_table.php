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
        Schema::table('invoices', function (Blueprint $table) {
            // Critical composite index for LR/Lorry Receipt filtering
            $table->index(['company_id', 'template_name'], 'idx_company_template');

            // Index for ordering by created_at within company
            $table->index(['company_id', 'created_at'], 'idx_company_created');

            // Individual indexes for common filters
            $table->index('template_name', 'idx_template_name');
            $table->index('customer_id', 'idx_customer_id');

            // Index for invoice number lookups
            $table->index('invoice_number', 'idx_invoice_number');

            // Index for status filtering
            $table->index('status', 'idx_status');
            $table->index('paid_status', 'idx_paid_status');

            // Add transport-specific indexes only if columns exist
            if (Schema::hasColumn('invoices', 'consignee_customer_id')) {
                $table->index('consignee_customer_id', 'idx_consignee_customer_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_company_template');
            $table->dropIndex('idx_company_created');
            $table->dropIndex('idx_template_name');
            $table->dropIndex('idx_customer_id');
            $table->dropIndex('idx_invoice_number');
            $table->dropIndex('idx_status');
            $table->dropIndex('idx_paid_status');

            if (Schema::hasColumn('invoices', 'consignee_customer_id')) {
                $table->dropIndex('idx_consignee_customer_id');
            }
        });
    }
};
