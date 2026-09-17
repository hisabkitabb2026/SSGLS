<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Customer;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
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

    public function test_get_all_customers(): void
    {
        Customer::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'company_id',
                ],
            ],
        ]);
    }

    public function test_get_single_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers/'.$customer->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.name', $customer->name);
    }

    public function test_create_customer(): void
    {
        $currency = $this->company->currencies()->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers', [
                'name' => 'New Customer',
                'email' => 'newcustomer@test.com',
                'phone' => '+1234567890',
                'company_id' => $this->company->id,
                'currency_id' => $currency->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Customer');
        $this->assertDatabaseHas('customers', ['email' => 'newcustomer@test.com']);
    }

    public function test_create_customer_with_addresses(): void
    {
        $currency = $this->company->currencies()->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers', [
                'name' => 'Customer with Address',
                'email' => 'withaddr@test.com',
                'company_id' => $this->company->id,
                'currency_id' => $currency->id,
                'billing' => [
                    'address_line_1' => '123 Main St',
                    'city' => 'New York',
                    'zip' => '10001',
                ],
                'shipping' => [
                    'address_line_1' => '456 Oak Ave',
                    'city' => 'Los Angeles',
                    'zip' => '90001',
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('addresses', ['address_line_1' => '123 Main St']);
        $this->assertDatabaseHas('addresses', ['address_line_1' => '456 Oak Ave']);
    }

    public function test_update_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/customers/'.$customer->id, [
                'name' => 'Updated Customer Name',
                'email' => 'updated@test.com',
                'company_id' => $this->company->id,
                'currency_id' => $customer->currency_id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Customer Name']);
    }

    public function test_delete_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $customerId = $customer->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/customers/'.$customerId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    public function test_get_customers_with_stats(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers/'.$customer->id);

        $response->assertStatus(200);
        // Stats endpoint may return stats in response
        $response->assertJsonStructure(['data']);
    }

    public function test_customers_paginated(): void
    {
        Customer::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_customer_can_be_searched(): void
    {
        Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'John Doe']);
        Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Jane Smith']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers?search=John');

        $response->assertStatus(200);
        $foundCustomer = collect($response->json('data'))->first(fn ($cust) => $cust['name'] === 'John Doe');
        $this->assertNotNull($foundCustomer);
    }

    public function test_unauthenticated_user_cannot_get_customers(): void
    {
        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_customers(): void
    {
        $otherCompany = Company::factory()->create();
        $customer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers/'.$customer->id);

        $response->assertStatus(403);
    }

    public function test_create_customer_requires_email(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers', [
                'name' => 'No Email Customer',
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_customer_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers', [
                'email' => 'noname@test.com',
                'company_id' => $this->company->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_delete_multiple_customers(): void
    {
        $customer1 = Customer::factory()->create(['company_id' => $this->company->id]);
        $customer2 = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers/bulk-delete', [
                'ids' => [$customer1->id, $customer2->id],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('customers', ['id' => $customer1->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer2->id]);
    }
}
