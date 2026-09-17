<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert LorryReceipt money columns from string to integer (cents).
 *
 * The lorry_receipts table originally stored all monetary amounts as strings
 * (e.g. '1500', '1,200.50'), while the rest of the codebase (invoices, estimates,
 * payments) stores money as integers in cents.  This caused the LorryReceipt
 * model to need a `numericAmount()` workaround method and made arithmetic
 * unreliable.
 *
 * This migration:
 *  1. Converts the string values to integer cents (× 100, stripping commas)
 *  2. Changes the column types from string to unsignedInteger
 *
 * After this migration, the model's `numericAmount()` method is no longer
 * needed and can be replaced with direct integer access.
 *
 * IMPORTANT: This is a one-way data migration.  Back up the database before
 * running.  The `down()` method restores the string columns but cannot
 * perfectly reconstruct the original string formatting (e.g. commas).
 *
 * @see LorryReceipt::numericAmount() — the workaround method being eliminated
 */
return new class extends Migration
{
    /**
     * Money columns to convert from string to integer (cents).
     */
    private array $moneyColumns = [
        'lorry_hire_amount',
        'other_charges_amount',
        'gross_hire_amount',
        'advance_amount',
        'balance_amount',
        'detention_amount',
        'extra_hire_amount',
        'final_other_amount',
        'final_total_extra_amount',
        'grand_total_amount',
        'less_advance_other_branch_amount',
        'less_deduction_claims_amount',
        'total_less_amount',
        'net_amount_payable',
        'rate',
    ];

    public function up(): void
    {
        // Step 1: Convert existing string values to integer cents using
        // PHP-side processing (works on both SQLite and MySQL — avoids
        // MySQL-specific CAST AS DECIMAL / CAST AS UNSIGNED syntax).
        $rows = DB::table('lorry_receipts')->get();

        foreach ($rows as $row) {
            $updates = [];
            foreach ($this->moneyColumns as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null || trim((string) $value) === '') {
                    $updates[$column] = null;
                } else {
                    // Strip commas and spaces, convert to float, then cents
                    $numeric = (float) str_replace([' ', ','], '', (string) $value);
                    $updates[$column] = (int) round($numeric * 100);
                }
            }
            if ($updates !== []) {
                DB::table('lorry_receipts')->where('id', $row->id)->update($updates);
            }
        }

        // Step 2: Change column types from string to unsignedInteger
        // On SQLite, ->change() is a no-op (SQLite uses dynamic typing),
        // so this only has an effect on MySQL/PostgreSQL.
        Schema::table('lorry_receipts', function (Blueprint $table) {
            foreach ($this->moneyColumns as $column) {
                $table->unsignedInteger($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Revert column types back to string
        Schema::table('lorry_receipts', function (Blueprint $table) {
            foreach ($this->moneyColumns as $column) {
                $table->string($column)->nullable()->change();
            }
        });

        // Convert integer cents back to decimal strings (divide by 100)
        $rows = DB::table('lorry_receipts')->get();

        foreach ($rows as $row) {
            $updates = [];
            foreach ($this->moneyColumns as $column) {
                $value = $row->{$column} ?? null;
                if ($value === null) {
                    $updates[$column] = null;
                } else {
                    $updates[$column] = (string) ((float) $value / 100);
                }
            }
            if ($updates !== []) {
                DB::table('lorry_receipts')->where('id', $row->id)->update($updates);
            }
        }
    }
};
