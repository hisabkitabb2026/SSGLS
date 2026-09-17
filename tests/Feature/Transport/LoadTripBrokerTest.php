<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Company;
use App\Models\ConsolidationGroup;
use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class LoadTripBrokerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
        $this->company = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->company);

        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company);
        BouncerFacade::scope()->to($this->company->id);
        $this->user->assign('owner');
    }

    public function test_can_create_load_trip_with_broker(): void
    {
        $truck = Truck::create([
            'company_id' => $this->company->id,
            'truck_number' => 'KA-01-AB-1234',
            'capacity_kg' => 9000,
            'status' => Truck::STATUS_AVAILABLE,
        ]);

        $driver = LorryPartyProfile::create([
            'company_id' => $this->company->id,
            'type' => LorryPartyProfile::TYPE_DRIVER,
            'name' => 'Ravi Kumar',
            'phone' => '9876543210',
        ]);

        $broker = LorryPartyProfile::create([
            'company_id' => $this->company->id,
            'type' => LorryPartyProfile::TYPE_BROKER,
            'name' => 'Shree Broker',
            'phone' => '9123456780',
        ]);

        $group = ConsolidationGroup::create([
            'company_id' => $this->company->id,
            'group_number' => 'CONS-2026-0001',
            'destination_city' => 'Mumbai',
            'truck_capacity_kg' => 9000,
            'status' => ConsolidationGroup::STATUS_READY,
        ]);

        $payload = [
            'consolidation_group_id' => $group->id,
            'truck_id' => $truck->id,
            'driver_profile_id' => $driver->id,
            'broker_profile_id' => $broker->id,
            'destination_city' => 'Mumbai',
            'origin_city' => 'Bangalore',
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/load-trips', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('load_trips', [
            'company_id' => $this->company->id,
            'broker_profile_id' => $broker->id,
            'broker_name' => 'Shree Broker',
            'broker_phone' => '9123456780',
        ]);
    }

    public function test_load_trip_resource_includes_broker_fields(): void
    {
        $truck = Truck::create([
            'company_id' => $this->company->id,
            'truck_number' => 'KA-02-CD-5678',
            'capacity_kg' => 9000,
            'status' => Truck::STATUS_AVAILABLE,
        ]);

        $driver = LorryPartyProfile::create([
            'company_id' => $this->company->id,
            'type' => LorryPartyProfile::TYPE_DRIVER,
            'name' => 'Ravi Kumar',
            'phone' => '9876543210',
        ]);

        $broker = LorryPartyProfile::create([
            'company_id' => $this->company->id,
            'type' => LorryPartyProfile::TYPE_BROKER,
            'name' => 'Shree Broker',
            'phone' => '9123456780',
        ]);

        $trip = LoadTrip::create([
            'company_id' => $this->company->id,
            'trip_number' => 'TRIP-2026-0001',
            'truck_id' => $truck->id,
            'driver_profile_id' => $driver->id,
            'broker_profile_id' => $broker->id,
            'truck_number' => $truck->truck_number,
            'driver_name' => $driver->name,
            'driver_phone' => $driver->phone,
            'broker_name' => $broker->name,
            'broker_phone' => $broker->phone,
            'destination_city' => 'Mumbai',
            'status' => LoadTrip::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson("/api/v1/load-trips/{$trip->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.broker_name', 'Shree Broker')
            ->assertJsonPath('data.broker_phone', '9123456780')
            ->assertJsonPath('data.broker_profile_id', $broker->id);
    }

    public function test_can_create_load_trip_without_broker(): void
    {
        $truck = Truck::create([
            'company_id' => $this->company->id,
            'truck_number' => 'KA-03-EF-9999',
            'capacity_kg' => 9000,
            'status' => Truck::STATUS_AVAILABLE,
        ]);

        $driver = LorryPartyProfile::create([
            'company_id' => $this->company->id,
            'type' => LorryPartyProfile::TYPE_DRIVER,
            'name' => 'Ravi Kumar',
            'phone' => '9876543210',
        ]);

        $group = ConsolidationGroup::create([
            'company_id' => $this->company->id,
            'group_number' => 'CONS-2026-0002',
            'destination_city' => 'Chennai',
            'truck_capacity_kg' => 9000,
            'status' => ConsolidationGroup::STATUS_READY,
        ]);

        $payload = [
            'consolidation_group_id' => $group->id,
            'truck_id' => $truck->id,
            'driver_profile_id' => $driver->id,
            'destination_city' => 'Chennai',
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/load-trips', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('load_trips', [
            'company_id' => $this->company->id,
            'broker_profile_id' => null,
            'broker_name' => null,
        ]);
    }
}
