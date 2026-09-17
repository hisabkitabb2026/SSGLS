<?php

namespace Tests\Unit;

use App\Domains\Customer\Application\CreateCustomerService;
use App\Domains\Customer\Data\CreateCustomerData;
use App\Models\Company;
use Tests\TestCase;

class CreateCustomerServiceTest extends TestCase
{
    private CreateCustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreateCustomerService::class);
    }

    public function test_can_create_customer(): void
    {
        $company = Company::factory()->create();

        $data = new CreateCustomerData(
            name: 'John Doe',
            email: 'john@example.com',
            phone: '555-1234',
            customer_type: 'individual',
            active: true,
            company_id: $company->id,
        );

        $customer = $this->service->execute($data);

        $this->assertNotNull($customer->id);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
    }
}
