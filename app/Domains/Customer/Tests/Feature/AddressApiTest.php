<?php

namespace Tests\Feature;

use App\Domains\Customer\Models\Address;
use App\Domains\Customer\Models\Customer;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class AddressApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_create_address(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/addresses', [
                'customer_id' => $customer->id,
                'street_address' => '123 Main St',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'is_primary' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.city', 'New York');
    }

    public function test_can_list_addresses_by_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        Address::factory()->count(2)->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$customer->id}/addresses");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
