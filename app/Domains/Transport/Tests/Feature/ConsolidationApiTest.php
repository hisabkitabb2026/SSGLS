<?php

namespace Tests\Feature;

use App\Domains\Transport\Models\ConsolidationGroup;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ConsolidationApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_consolidations(): void
    {
        ConsolidationGroup::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/consolidations');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_consolidation(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/consolidations', [
                'name' => 'Consolidation 1',
                'destination' => 'City A',
                'status' => 'pending',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Consolidation 1');
    }

    public function test_can_show_consolidation(): void
    {
        $consolidation = ConsolidationGroup::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/consolidations/{$consolidation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $consolidation->id);
    }

    public function test_can_update_consolidation(): void
    {
        $consolidation = ConsolidationGroup::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/consolidations/{$consolidation->id}", [
                'status' => 'in_transit',
            ]);

        $response->assertOk();
    }

    public function test_can_delete_consolidation(): void
    {
        $consolidation = ConsolidationGroup::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/consolidations/{$consolidation->id}");

        $response->assertNoContent();
    }
}
