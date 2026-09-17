<?php

namespace Tests\Feature;

use App\Domains\Settings\Models\Currency;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class CurrencyApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_currencies(): void
    {
        Currency::factory()->count(3)->create();
        $response = $this->actingAs($this->user)->getJson('/api/v1/currencies');
        $response->assertOk();
    }

    public function test_can_create_currency(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/currencies', ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$']);
        $response->assertCreated();
    }
}
