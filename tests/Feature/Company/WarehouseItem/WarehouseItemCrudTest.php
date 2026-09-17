<?php

declare(strict_types=1);

namespace Tests\Feature\Company\WarehouseItem;

use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class WarehouseItemCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Item $item;

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

        $this->item = Item::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_get_all_warehouse_items(): void
    {
        WarehouseItem::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/warehouse-items');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'item_id',
                    'company_id',
                ],
            ],
        ]);
    }

    public function test_get_single_warehouse_item(): void
    {
        $warehouseItem = WarehouseItem::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/warehouse-items/'.$warehouseItem->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $warehouseItem->id);
        $response->assertJsonPath('data.item_id', $warehouseItem->item_id);
    }

    public function test_create_warehouse_item(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'item_id' => $this->item->id,
                'quantity' => 100,
                'opening_quantity' => 50,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.item_id', $this->item->id);
        $this->assertDatabaseHas('warehouse_items', ['item_id' => $this->item->id]);
    }

    public function test_create_warehouse_item_with_quantity(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'item_id' => $this->item->id,
                'quantity' => 250,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouse_items', ['item_id' => $this->item->id, 'quantity' => 250]);
    }

    public function test_update_warehouse_item(): void
    {
        $warehouseItem = WarehouseItem::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/warehouse-items/'.$warehouseItem->id, [
                'item_id' => $warehouseItem->item_id,
                'quantity' => 500,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouse_items', ['id' => $warehouseItem->id, 'quantity' => 500]);
    }

    public function test_delete_warehouse_item(): void
    {
        $warehouseItem = WarehouseItem::factory()->create(['company_id' => $this->company->id]);
        $itemId = $warehouseItem->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/warehouse-items/'.$itemId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('warehouse_items', ['id' => $itemId]);
    }

    public function test_warehouse_items_paginated(): void
    {
        WarehouseItem::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/warehouse-items?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_warehouse_item_can_be_searched_by_item_name(): void
    {
        $item1 = Item::factory()->create(['company_id' => $this->company->id, 'name' => 'Search Item']);
        $item2 = Item::factory()->create(['company_id' => $this->company->id, 'name' => 'Other Item']);

        WarehouseItem::factory()->create(['company_id' => $this->company->id, 'item_id' => $item1->id]);
        WarehouseItem::factory()->create(['company_id' => $this->company->id, 'item_id' => $item2->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/warehouse-items?search=Search');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_unauthenticated_user_cannot_get_warehouse_items(): void
    {
        $response = $this->getJson('/api/v1/warehouse-items');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_warehouse_items(): void
    {
        $otherCompany = Company::factory()->create();
        $otherItem = Item::factory()->create(['company_id' => $otherCompany->id]);
        $warehouseItem = WarehouseItem::factory()->create(['company_id' => $otherCompany->id, 'item_id' => $otherItem->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/warehouse-items/'.$warehouseItem->id);

        $response->assertStatus(403);
    }

    public function test_create_warehouse_item_requires_item_id(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'quantity' => 100,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['item_id']);
    }

    public function test_create_warehouse_item_requires_quantity(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'item_id' => $this->item->id,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['quantity']);
    }

    public function test_warehouse_item_with_zero_quantity(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'item_id' => $this->item->id,
                'quantity' => 0,
                'company_id' => $this->company->id,
            ]);

        // Should allow zero quantity
        $response->assertStatus(200);
    }

    public function test_warehouse_item_preserves_item_relationship(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/warehouse-items', [
                'item_id' => $this->item->id,
                'quantity' => 100,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $warehouseItemId = $response->json('data.id');

        $warehouseItem = WarehouseItem::find($warehouseItemId);
        $warehouseItem->load('item');

        $this->assertEquals($this->item->id, $warehouseItem->item->id);
    }
}
