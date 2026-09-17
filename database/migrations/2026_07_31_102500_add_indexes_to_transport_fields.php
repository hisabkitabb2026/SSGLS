<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Skip - indexes are managed by Laravel migrations with composite keys
        // and text columns have key length limitations on MySQL
    }

    public function down(): void
    {
        // No-op migration
    }
};
