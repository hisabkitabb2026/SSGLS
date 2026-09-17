<?php

namespace Tests\Unit;

use App\Domains\Transport\Application\CreateLorryReceiptService;
use App\Domains\Transport\Data\CreateLorryReceiptData;
use App\Models\Company;
use Tests\TestCase;

class CreateLorryReceiptServiceTest extends TestCase
{
    private CreateLorryReceiptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreateLorryReceiptService::class);
    }

    public function test_can_create_lorry_receipt(): void
    {
        $company = Company::factory()->create();

        $data = new CreateLorryReceiptData(
            vehicle_number: 'TEST-001',
            owner_name: 'John Doe',
            driver_name: 'Jane Smith',
            from_location: 'City A',
            to_location: 'City B',
            freight_amount: 5000.00,
            company_id: $company->id,
        );

        $receipt = $this->service->execute($data);

        $this->assertNotNull($receipt->id);
        $this->assertEquals('TEST-001', $receipt->vehicle_number);
        $this->assertEquals($company->id, $receipt->company_id);
    }
}
