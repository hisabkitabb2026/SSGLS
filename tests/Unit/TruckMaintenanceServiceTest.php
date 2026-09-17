<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\LoadTrip;
use App\Models\Truck;
use App\Models\TruckMaintenance;
use App\Models\User;
use App\Services\Document\TruckMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    User::factory()->create(['role' => 'super admin']);
});

it('puts a truck into maintenance and releases it after completion', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create([
        'company_id' => $company->id,
        'truck_number' => 'KA01AB1234',
        'capacity_kg' => 5000,
        'status' => Truck::STATUS_AVAILABLE,
    ]);

    $service = app(TruckMaintenanceService::class);
    $maintenance = $service->create($company->id, [
        'truck_id' => $truck->id,
        'type' => 'Scheduled service',
        'status' => 'in_progress',
    ]);

    expect($truck->fresh()->status)->toBe(Truck::STATUS_MAINTENANCE);

    $service->update($maintenance, ['status' => 'completed', 'completed_date' => now()->toDateString()]);

    expect($truck->fresh()->status)->toBe(Truck::STATUS_AVAILABLE)
        ->and($maintenance->fresh()->status)->toBe('completed');
});

it('does not allow maintenance to begin while the truck has an active trip', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create([
        'company_id' => $company->id,
        'truck_number' => 'KA01AB1234',
        'capacity_kg' => 5000,
        'status' => Truck::STATUS_RESERVED,
    ]);
    LoadTrip::create([
        'company_id' => $company->id,
        'truck_id' => $truck->id,
        'trip_number' => 'TRIP-2026-0001',
        'destination_city' => 'Bengaluru',
        'status' => LoadTrip::STATUS_PLANNED,
    ]);

    app(TruckMaintenanceService::class)->create($company->id, [
        'truck_id' => $truck->id,
        'type' => 'Tyre replacement',
        'status' => 'in_progress',
    ]);
})->throws(ValidationException::class, 'The selected truck has an active load trip');

it('does not allow an in-progress maintenance record to be deleted', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create([
        'company_id' => $company->id,
        'truck_number' => 'KA01AB1234',
        'capacity_kg' => 5000,
    ]);
    $maintenance = TruckMaintenance::create([
        'company_id' => $company->id,
        'truck_id' => $truck->id,
        'type' => 'Repair',
        'status' => 'in_progress',
    ]);

    app(TruckMaintenanceService::class)->delete($maintenance);
})->throws(ValidationException::class, 'must be completed or cancelled');
