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
        Schema::create('truck_maintenances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('truck_id')->index();
            $table->string('type');
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->string('status')->default('scheduled');
            $table->decimal('cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('truck_maintenances');
    }
};
