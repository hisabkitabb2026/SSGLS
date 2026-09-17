<?php

namespace Tests\Feature;

use App\Domains\Transport\Models\LorryReceipt;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class LorryReceiptApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_lorry_receipts(): void
    {
        LorryReceipt::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/lorry-receipts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_lorry_receipt(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/lorry-receipts', [
                'vehicle_number' => 'ABC-123',
                'owner_name' => 'John Doe',
                'driver_name' => 'Jane Smith',
                'from_location' => 'City A',
                'to_location' => 'City B',
                'freight_amount' => 5000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.vehicle_number', 'ABC-123');
    }

    public function test_can_show_lorry_receipt(): void
    {
        $receipt = LorryReceipt::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/lorry-receipts/{$receipt->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $receipt->id);
    }

    public function test_can_update_lorry_receipt(): void
    {
        $receipt = LorryReceipt::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/lorry-receipts/{$receipt->id}", [
                'status' => 'delivered',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('lorry_receipts', [
            'id' => $receipt->id,
            'status' => 'delivered',
        ]);
    }

    public function test_can_delete_lorry_receipt(): void
    {
        $receipt = LorryReceipt::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/lorry-receipts/{$receipt->id}");

        $response->assertNoContent();
        $this->assertModelMissing($receipt);
    }
}
