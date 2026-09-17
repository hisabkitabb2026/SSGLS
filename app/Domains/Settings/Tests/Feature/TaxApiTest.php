<?php

namespace Tests\Feature;

use App\Domains\Settings\Models\Tax;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_taxes(): void
    {
        Tax::factory()->count(3)->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/taxes');
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_create_tax(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/taxes', ['name' => 'VAT', 'rate' => 18]);
        $response->assertCreated();
    }
}
