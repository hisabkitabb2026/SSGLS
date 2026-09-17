<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use App\Models\User;
use App\Services\Document\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    User::factory()->create(['role' => 'super admin']);
});

it('accepts fleet links that match the selected load trip', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000]);
    $driver = LorryPartyProfile::create(['company_id' => $company->id, 'type' => LorryPartyProfile::TYPE_DRIVER, 'name' => 'Ravi Kumar']);
    $trip = LoadTrip::create(['company_id' => $company->id, 'truck_id' => $truck->id, 'driver_profile_id' => $driver->id, 'trip_number' => 'TRIP-2026-0001', 'destination_city' => 'Bengaluru', 'status' => LoadTrip::STATUS_PLANNED]);

    app(ExpenseService::class)->validateFleetLinks(['truck_id' => $truck->id, 'load_trip_id' => $trip->id, 'driver_profile_id' => $driver->id], $company->id);

    expect(true)->toBeTrue();
});

it('rejects a truck that does not belong to the selected trip', function (): void {
    $company = Company::factory()->create();
    $tripTruck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000]);
    $otherTruck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA02AB1234', 'capacity_kg' => 5000]);
    $trip = LoadTrip::create(['company_id' => $company->id, 'truck_id' => $tripTruck->id, 'trip_number' => 'TRIP-2026-0001', 'destination_city' => 'Bengaluru', 'status' => LoadTrip::STATUS_PLANNED]);

    app(ExpenseService::class)->validateFleetLinks(['truck_id' => $otherTruck->id, 'load_trip_id' => $trip->id], $company->id);
})->throws(ValidationException::class, 'does not match the selected load trip');
