<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pre-seed standard vehicle weight/capacity units used in logistics
     * quotations. These appear in the "Weight" dropdown when creating items
     * (station rates). company_id is NULL so they are shared across all
     * companies — a company-specific copy can be created later if needed.
     */
    public function up(): void
    {
        $vehicleWeights = [
            '9 MT',
            '10 MT',
            '12 MT',
            '15 MT',
            '18 MT',
            '24 MT',
            '30 MT',
        ];

        foreach ($vehicleWeights as $weight) {
            DB::table('units')->insertOrIgnore([
                'name' => $weight,
                'company_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('units')
            ->whereIn('name', ['9 MT', '10 MT', '12 MT', '15 MT', '18 MT', '24 MT', '30 MT'])
            ->whereNull('company_id')
            ->delete();
    }
};
