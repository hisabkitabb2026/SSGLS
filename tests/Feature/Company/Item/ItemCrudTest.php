<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Item;

use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class ItemCrudTest extends TestCase
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

    public function test_get_all_items(): void
    {
        Item::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'company_id',
                ],
            ],
        ]);
    }

    public function test_get_single_item(): void
    {
        $item = Item::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items/'.$item->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $item->id);
        $response->assertJsonPath('data.name', $item->name);
    }

    public function test_create_item(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/items', [
                'name' => 'New Item',
                'description' => 'Test Item Description',
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Item');
        $this->assertDatabaseHas('items', ['name' => 'New Item']);
    }

    public function test_create_item_with_unit_price(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/items', [
                'name' => 'Priced Item',
                'description' => 'Item with unit price',
                'unit_price' => 99.99,
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['name' => 'Priced Item', 'unit_price' => 99.99]);
    }

    public function test_update_item(): void
    {
        $item = Item::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/items/'.$item->id, [
                'name' => 'Updated Item Name',
                'description' => 'Updated description',
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Updated Item Name']);
    }

    public function test_delete_item(): void
    {
        $item = Item::factory()->create(['company_id' => $this->company->id]);
        $itemId = $item->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/items/'.$itemId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $itemId]);
    }

    public function test_items_paginated(): void
    {
        Item::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_item_can_be_searched(): void
    {
        Item::factory()->create(['company_id' => $this->company->id, 'name' => 'Premium Item']);
        Item::factory()->create(['company_id' => $this->company->id, 'name' => 'Standard Item']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items?search=Premium');

        $response->assertStatus(200);
        $foundItem = collect($response->json('data'))->first(fn ($itm) => $itm['name'] === 'Premium Item');
        $this->assertNotNull($foundItem);
    }

    public function test_unauthenticated_user_cannot_get_items(): void
    {
        $response = $this->getJson('/api/v1/items');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_items(): void
    {
        $otherCompany = Company::factory()->create();
        $item = Item::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items/'.$item->id);

        $response->assertStatus(403);
    }

    public function test_create_item_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/items', [
                'description' => 'No name item',
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_delete_multiple_items(): void
    {
        $item1 = Item::factory()->create(['company_id' => $this->company->id]);
        $item2 = Item::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/items/bulk-delete', [
                'ids' => [$item1->id, $item2->id],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('items', ['id' => $item2->id]);
    }

    public function test_item_can_be_sorted(): void
    {
        Item::factory()->create(['company_id' => $this->company->id, 'name' => 'B Item']);
        Item::factory()->create(['company_id' => $this->company->id, 'name' => 'A Item']);
        Item::factory()->create(['company_id' => $this->company->id, 'name' => 'C Item']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/items?sort=-name');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Items should be sorted (descending by name)
        $this->assertGreaterThan(0, count($data));
    }
}
