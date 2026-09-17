<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $service;

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

        $this->service = app(CustomerService::class);
    }

    public function test_create_customer_with_basic_info(): void
    {
        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'phone' => '+1234567890',
            'company_id' => $this->company->id,
        ]);

        $customer = $this->service->create($request);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('Test Customer', $customer->name);
        $this->assertEquals('customer@test.com', $customer->email);
        $this->assertEquals($this->company->id, $customer->company_id);
    }

    public function test_create_customer_with_billing_address(): void
    {
        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Customer with Address',
            'email' => 'customer@test.com',
            'company_id' => $this->company->id,
            'billing' => [
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'zip' => '10001',
            ],
        ]);

        $customer = $this->service->create($request);
        $customer->load('billingAddress');

        $this->assertNotNull($customer->billingAddress);
        $this->assertEquals('123 Main St', $customer->billingAddress->address_line_1);
    }

    public function test_create_customer_with_shipping_address(): void
    {
        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Customer with Shipping',
            'email' => 'customer@test.com',
            'company_id' => $this->company->id,
            'shipping' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Los Angeles',
                'zip' => '90001',
            ],
        ]);

        $customer = $this->service->create($request);
        $customer->load('shippingAddress');

        $this->assertNotNull($customer->shippingAddress);
        $this->assertEquals('456 Oak Ave', $customer->shippingAddress->address_line_1);
    }

    public function test_create_customer_with_custom_fields(): void
    {
        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Customer with Fields',
            'email' => 'customer@test.com',
            'company_id' => $this->company->id,
            'customFields' => [
                'field1' => 'value1',
                'field2' => 'value2',
            ],
        ]);

        $customer = $this->service->create($request);
        $customer->load('fields');

        $this->assertNotNull($customer->fields);
    }

    public function test_update_customer_with_new_address(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $request = Request::create('/api/v1/customers/'.$customer->id, 'PUT', [
            'name' => 'Updated Customer',
            'email' => 'updated@test.com',
            'company_id' => $this->company->id,
            'billing' => [
                'address_line_1' => 'New Address',
                'city' => 'Boston',
                'zip' => '02101',
            ],
        ]);

        $updatedCustomer = $this->service->update($request, $customer);

        $this->assertEquals('Updated Customer', $updatedCustomer->name);
        $this->assertEquals('updated@test.com', $updatedCustomer->email);
        $this->assertEquals('New Address', $updatedCustomer->billingAddress->address_line_1);
    }

    public function test_update_customer_removes_old_addresses(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $customer->addresses()->create([
            'address_type' => 'billing',
            'address_line_1' => 'Old Address',
        ]);

        $request = Request::create('/api/v1/customers/'.$customer->id, 'PUT', [
            'name' => 'Updated',
            'email' => 'updated@test.com',
            'company_id' => $this->company->id,
            'billing' => [
                'address_line_1' => 'New Address',
                'city' => 'Boston',
            ],
        ]);

        $updatedCustomer = $this->service->update($request, $customer);
        $updatedCustomer->load('addresses');

        $this->assertCount(1, $updatedCustomer->addresses);
        $this->assertEquals('New Address', $updatedCustomer->addresses[0]->address_line_1);
    }

    public function test_cannot_update_currency_if_customer_has_invoices(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'company_id' => $this->company->id]);

        $newCurrency = $this->company->currencies()->first();
        if ($newCurrency->id === $customer->currency_id) {
            $newCurrency = $this->company->currencies()->where('id', '!=', $customer->currency_id)->first();
        }

        $request = Request::create('/api/v1/customers/'.$customer->id, 'PUT', [
            'name' => $customer->name,
            'email' => $customer->email,
            'company_id' => $this->company->id,
            'currency_id' => $newCurrency->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->update($request, $customer);
    }

    public function test_delete_customer_removes_related_records(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'company_id' => $this->company->id]);
        $customerId = $customer->id;

        $this->service->delete(collect([$customerId]));

        $this->assertFalse(Customer::where('id', $customerId)->exists());
        $this->assertFalse(Invoice::where('customer_id', $customerId)->exists());
    }

    public function test_delete_multiple_customers(): void
    {
        $customer1 = Customer::factory()->create(['company_id' => $this->company->id]);
        $customer2 = Customer::factory()->create(['company_id' => $this->company->id]);

        $ids = collect([$customer1->id, $customer2->id]);
        $this->service->delete($ids);

        $this->assertFalse(Customer::whereIn('id', [$customer1->id, $customer2->id])->exists());
    }

    public function test_get_customer_stats(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $stats = $this->service->getStats($customer, $this->company->id);

        $this->assertIsArray($stats);
    }

    public function test_update_customer_with_custom_fields(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $request = Request::create('/api/v1/customers/'.$customer->id, 'PUT', [
            'name' => 'Updated',
            'email' => 'updated@test.com',
            'company_id' => $this->company->id,
            'customFields' => [
                'field1' => 'new_value1',
                'field2' => 'new_value2',
            ],
        ]);

        $updatedCustomer = $this->service->update($request, $customer);
        $updatedCustomer->load('fields');

        $this->assertNotNull($updatedCustomer->fields);
    }

    public function test_create_customer_with_both_addresses(): void
    {
        $request = Request::create('/api/v1/customers', 'POST', [
            'name' => 'Multi Address Customer',
            'email' => 'customer@test.com',
            'company_id' => $this->company->id,
            'billing' => [
                'address_line_1' => '123 Main St',
                'city' => 'New York',
            ],
            'shipping' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Los Angeles',
            ],
        ]);

        $customer = $this->service->create($request);
        $customer->load('addresses');

        $this->assertCount(2, $customer->addresses);
    }
}
