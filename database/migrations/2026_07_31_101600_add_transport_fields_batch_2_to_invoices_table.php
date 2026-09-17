<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
Batch 2: Lorry Receipt Vehicles, Fitness, Insurance & Owner Details (30 columns)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // ── Lorry Receipt: Vehicle Details (continued) ──
            if (! Schema::hasColumn('invoices', 'chasis_no')) {
                $table->text('chasis_no')->nullable()->after('colour');
            }
            if (! Schema::hasColumn('invoices', 'engine_no')) {
                $table->text('engine_no')->nullable()->after('chasis_no');
            }

            // ── Lorry Receipt: Fitness & Permit ──
            if (! Schema::hasColumn('invoices', 'fitness_validity')) {
                $table->text('fitness_validity')->nullable()->after('engine_no');
            }
            if (! Schema::hasColumn('invoices', 'road_permit_no')) {
                $table->text('road_permit_no')->nullable()->after('fitness_validity');
            }
            if (! Schema::hasColumn('invoices', 'permit_date')) {
                $table->text('permit_date')->nullable()->after('road_permit_no');
            }
            if (! Schema::hasColumn('invoices', 'permit_valid_in')) {
                $table->text('permit_valid_in')->nullable()->after('permit_date');
            }
            if (! Schema::hasColumn('invoices', 'permit_status_upto')) {
                $table->text('permit_status_upto')->nullable()->after('permit_valid_in');
            }

            // ── Lorry Receipt: Insurance ──
            if (! Schema::hasColumn('invoices', 'insured_with')) {
                $table->text('insured_with')->nullable()->after('permit_status_upto');
            }
            if (! Schema::hasColumn('invoices', 'insurance_division_no')) {
                $table->text('insurance_division_no')->nullable()->after('insured_with');
            }
            if (! Schema::hasColumn('invoices', 'insurance_certificate_no')) {
                $table->text('insurance_certificate_no')->nullable()->after('insurance_division_no');
            }
            if (! Schema::hasColumn('invoices', 'insurance_valid_upto')) {
                $table->text('insurance_valid_upto')->nullable()->after('insurance_certificate_no');
            }

            // ── Lorry Receipt: Owner Details ──
            if (! Schema::hasColumn('invoices', 'owner_code')) {
                $table->text('owner_code')->nullable()->after('insurance_valid_upto');
            }
            if (! Schema::hasColumn('invoices', 'owner_name')) {
                $table->text('owner_name')->nullable()->after('owner_code');
            }
            if (! Schema::hasColumn('invoices', 'owner_address')) {
                $table->text('owner_address')->nullable()->after('owner_name');
            }
            if (! Schema::hasColumn('invoices', 'owner_phone')) {
                $table->text('owner_phone')->nullable()->after('owner_address');
            }
            if (! Schema::hasColumn('invoices', 'owner_bank_account_no')) {
                $table->text('owner_bank_account_no')->nullable()->after('owner_phone');
            }
            if (! Schema::hasColumn('invoices', 'owner_pan_no')) {
                $table->text('owner_pan_no')->nullable()->after('owner_bank_account_no');
            }

            // ── Lorry Receipt: Financer ──
            if (! Schema::hasColumn('invoices', 'financer_name')) {
                $table->text('financer_name')->nullable()->after('owner_pan_no');
            }
            if (! Schema::hasColumn('invoices', 'financer_address')) {
                $table->text('financer_address')->nullable()->after('financer_name');
            }

            // ── Lorry Receipt: Driver Details ──
            if (! Schema::hasColumn('invoices', 'driver_name')) {
                $table->text('driver_name')->nullable()->after('financer_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_address')) {
                $table->text('driver_address')->nullable()->after('driver_name');
            }
            if (! Schema::hasColumn('invoices', 'driver_place')) {
                $table->text('driver_place')->nullable()->after('driver_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_no')) {
                $table->text('driver_licence_no')->nullable()->after('driver_place');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_date')) {
                $table->text('driver_licence_date')->nullable()->after('driver_licence_no');
            }
            if (! Schema::hasColumn('invoices', 'driver_licence_issued_by')) {
                $table->text('driver_licence_issued_by')->nullable()->after('driver_licence_date');
            }
            if (! Schema::hasColumn('invoices', 'driver_rto_address')) {
                $table->text('driver_rto_address')->nullable()->after('driver_licence_issued_by');
            }
            if (! Schema::hasColumn('invoices', 'driver_valid_up_to')) {
                $table->text('driver_valid_up_to')->nullable()->after('driver_rto_address');
            }
            if (! Schema::hasColumn('invoices', 'driver_bank_account_no')) {
                $table->text('driver_bank_account_no')->nullable()->after('driver_valid_up_to');
            }
        });
    }

    public function down(): void
    {
        $columns = [
            'chasis_no', 'engine_no',
            'fitness_validity', 'road_permit_no', 'permit_date',
            'permit_valid_in', 'permit_status_upto',
            'insured_with', 'insurance_division_no', 'insurance_certificate_no',
            'insurance_valid_upto',
            'owner_code', 'owner_name', 'owner_address', 'owner_phone',
            'owner_bank_account_no', 'owner_pan_no',
            'financer_name', 'financer_address',
            'driver_name', 'driver_address', 'driver_place', 'driver_licence_no',
            'driver_licence_date', 'driver_licence_issued_by', 'driver_rto_address',
            'driver_valid_up_to', 'driver_bank_account_no',
        ];

        $existing = array_filter($columns, fn ($col) => Schema::hasColumn('invoices', $col));
        if (! empty($existing)) {
            Schema::table('invoices', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
