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
        Schema::create('lorry_party_profiles', function (Blueprint $table) {
            $table->id();

            // company_id references INT UNSIGNED companies.id
            $table->unsignedInteger('company_id')->index();

            // customer_id references BIGINT customers.id (optional link)
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            // OWNER, DRIVER, BROKER
            $table->string('type');

            // Basic info
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();

            // Owner-specific
            $table->string('financer_name')->nullable();
            $table->text('financer_address')->nullable();
            $table->string('place')->nullable();
            $table->string('bank_account_no')->nullable();

            // Driver-specific
            $table->string('licence_no')->nullable();
            $table->date('licence_date')->nullable();
            $table->string('licence_issued_by')->nullable();
            $table->text('rto_address')->nullable();
            $table->date('valid_up_to')->nullable();

            // Broker-specific
            $table->string('advice_no')->nullable();
            $table->date('advice_date')->nullable();
            $table->string('destination_broker_name')->nullable();
            $table->text('destination_broker_address')->nullable();

            // Document paths (Owner)
            $table->string('rc_front_path')->nullable();
            $table->string('rc_back_path')->nullable();
            $table->string('pan_front_path')->nullable();
            $table->string('insurance_path')->nullable();

            // Document paths (Driver)
            $table->string('license_front_path')->nullable();
            $table->string('license_back_path')->nullable();

            // Document paths (Broker)
            $table->string('pan_front_path_broker')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lorry_party_profiles');
    }
};
