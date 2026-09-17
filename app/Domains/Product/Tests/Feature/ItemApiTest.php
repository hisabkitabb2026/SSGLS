<?php

namespace Tests\Feature;

use App\Domains\Product\Models\Item;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ItemApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_items(): void
    {
        Item::factory()->count(3)->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/items');
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_create_item(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/items', ['name' => 'Test Item', 'price' => 100, 'unit_id' => 1]);
        $response->assertCreated();
    }
}
