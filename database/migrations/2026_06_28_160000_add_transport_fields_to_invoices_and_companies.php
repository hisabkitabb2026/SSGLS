<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds transport/logistics fields to existing tables.
     * All columns are nullable and additive — no existing data is affected.
     */
    public function up(): void
    {
        // Add consignee_customer_id to invoices (customers.id is BIGINT UNSIGNED)
        // No DB-level FK constraint — per codebase convention relationships are
        // handled in app code.
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('consignee_customer_id')->nullable()->index()->after('customer_id');
        });

        // Add transport company fields
        Schema::table('companies', function (Blueprint $table) {
            $table->string('top_heading')->nullable()->after('logo');
            $table->string('billing_branch')->nullable()->after('top_heading');
            $table->string('enrollment_no')->nullable()->after('billing_branch');
            $table->string('document_identity')->nullable()->after('enrollment_no');
        });

        // Add customer type field (consignor/consignee)
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name')->comment('CONSIGNOR, CONSIGNEE, or null for regular');
            $table->string('bank_account_no')->nullable()->after('type');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['consignee_customer_id']);
            $table->dropColumn('consignee_customer_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['top_heading', 'billing_branch', 'enrollment_no', 'document_identity']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'bank_account_no']);
        });
    }
};
