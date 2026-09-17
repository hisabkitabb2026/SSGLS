<?php

namespace Tests\Feature;

use App\Domains\Transport\Models\WarehouseItem;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class WarehouseItemApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_warehouse_items(): void
    {
        WarehouseItem::factory()->count(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/warehouse-items');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_create_warehouse_item(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/warehouse-items', [
                'destination' => 'Warehouse A',
                'quantity' => 100,
                'weight' => 500.50,
                'status' => 'pending',
            ]);

        $response->assertCreated();
    }

    public function test_can_update_warehouse_item_status(): void
    {
        $item = WarehouseItem::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/warehouse-items/{$item->id}/status", [
                'status' => 'shipped',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'status' => 'shipped',
        ]);
    }
}
