<?php

namespace Tests\Feature;

use App\Domains\Customer\Models\Customer;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_customer(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/customers', [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '555-5678',
                'customer_type' => 'business',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Jane Smith');
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/customers/{$customer->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk();
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertNoContent();
    }
}
