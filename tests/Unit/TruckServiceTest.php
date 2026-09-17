<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Company;
use App\Models\LoadTrip;
use App\Models\Truck;
use App\Models\User;
use App\Services\Document\TruckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TruckServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_truck_when_its_capacity_is_less_than_the_load(): void
    {
        User::factory()->create(['role' => 'super admin']);
        $company = Company::factory()->create();
        $truck = Truck::create([
            'company_id' => $company->id,
            'truck_number' => 'KA01AB1234',
            'capacity_kg' => 5000,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected truck does not have enough capacity for this load.');

        app(TruckService::class)->ensureAvailableForLoad($truck, 6000);
    }

    public function test_it_blocks_deletion_when_truck_has_active_trips(): void
    {
        User::factory()->create(['role' => 'super admin']);
        $company = Company::factory()->create();
        $truck = Truck::create([
            'company_id' => $company->id,
            'truck_number' => 'KA01AB1234',
            'capacity_kg' => 5000,
        ]);

        LoadTrip::create([
            'company_id' => $company->id,
            'truck_id' => $truck->id,
            'trip_number' => 'TRIP-2025-0001',
            'destination_city' => 'Mumbai',
            'status' => LoadTrip::STATUS_PLANNED,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot delete a truck that has active (planned or dispatched) trips.');

        app(TruckService::class)->deleteTruck($truck);
    }

    public function test_it_allows_deletion_when_truck_has_no_active_trips(): void
    {
        User::factory()->create(['role' => 'super admin']);
        $company = Company::factory()->create();
        $truck = Truck::create([
            'company_id' => $company->id,
            'truck_number' => 'KA01AB1234',
            'capacity_kg' => 5000,
        ]);

        LoadTrip::create([
            'company_id' => $company->id,
            'truck_id' => $truck->id,
            'trip_number' => 'TRIP-2025-0002',
            'destination_city' => 'Mumbai',
            'status' => LoadTrip::STATUS_DELIVERED,
        ]);

        $result = app(TruckService::class)->deleteTruck($truck);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('trucks', ['id' => $truck->id]);
    }
}
