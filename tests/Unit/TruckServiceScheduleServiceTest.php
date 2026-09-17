<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Truck;
use App\Models\User;
use App\Services\Document\TruckServiceScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    User::factory()->create(['role' => 'super admin']);
});

it('calculates a kilometre service due date and rolls it forward after completion', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000, 'current_odometer_km' => 10000]);
    $service = app(TruckServiceScheduleService::class);
    $schedule = $service->create($company->id, ['truck_id' => $truck->id, 'name' => 'Engine oil service', 'interval_km' => 10000]);

    expect($schedule->next_due_odometer_km)->toBe(20000)->and($service->getStatus($schedule))->toBe('scheduled');

    $truck->update(['current_odometer_km' => 20000]);
    expect($service->getStatus($schedule->fresh('truck')))->toBe('overdue');

    $completed = $service->complete($schedule, ['service_odometer_km' => 20000]);
    expect($completed->next_due_odometer_km)->toBe(30000);
});
