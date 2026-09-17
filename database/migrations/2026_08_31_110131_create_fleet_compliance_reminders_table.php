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
        Schema::create('fleet_compliance_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('truck_id')->nullable()->index();
            $table->unsignedInteger('driver_profile_id')->nullable()->index();
            $table->string('document_type');
            $table->date('expires_on');
            $table->integer('reminder_days');
            $table->date('sent_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_compliance_reminders');
    }
};
