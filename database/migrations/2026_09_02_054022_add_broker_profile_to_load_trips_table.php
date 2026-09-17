<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Broker profile link and denormalized broker fields to load_trips.
 *
 * Mirrors the existing driver_profile_id / driver_name / driver_phone pattern.
 * The broker is resolved from LorryPartyProfile (type = BROKER) and the
 * human-readable name/phone are denormalized for fast board rendering and
 * historical accuracy (even if the profile is later edited).
 *
 * Note: Some columns (broker_name, broker_profile_id) may already exist from
 * a partial run, so we guard each with hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('load_trips', function (Blueprint $table) {
            if (! Schema::hasColumn('load_trips', 'broker_profile_id')) {
                // LorryPartyProfile FK (INT UNSIGNED — matches lorry_party_profiles.id)
                $table->unsignedInteger('broker_profile_id')->nullable();
                $table->index('broker_profile_id');
            }

            if (! Schema::hasColumn('load_trips', 'broker_name')) {
                $table->string('broker_name')->nullable();
            }

            if (! Schema::hasColumn('load_trips', 'broker_phone')) {
                $table->string('broker_phone')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('load_trips', function (Blueprint $table) {
            if (Schema::hasColumn('load_trips', 'broker_profile_id')) {
                $table->dropIndex(['broker_profile_id']);
            }
            $table->dropColumn(['broker_profile_id', 'broker_name', 'broker_phone']);
        });
    }
};
