<?php

namespace Tests\Feature;

use App\Domains\Transport\Models\ConsolidationGroup;
use App\Domains\Transport\Models\LoadTrip;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class LoadTripApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_load_trips(): void
    {
        LoadTrip::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/load-trips');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_load_trip(): void
    {
        $consolidation = ConsolidationGroup::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/load-trips', [
                'name' => 'Trip 1',
                'consolidation_id' => $consolidation->id,
                'status' => 'pending',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Trip 1');
    }

    public function test_can_show_load_trip(): void
    {
        $trip = LoadTrip::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/load-trips/{$trip->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $trip->id);
    }

    public function test_can_update_load_trip(): void
    {
        $trip = LoadTrip::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/load-trips/{$trip->id}", [
                'status' => 'in_transit',
            ]);

        $response->assertOk();
    }

    public function test_can_delete_load_trip(): void
    {
        $trip = LoadTrip::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/load-trips/{$trip->id}");

        $response->assertNoContent();
    }
}
