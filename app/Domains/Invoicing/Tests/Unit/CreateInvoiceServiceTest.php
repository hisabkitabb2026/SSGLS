<?php

namespace Tests\Unit;

use App\Domains\Invoicing\Application\CreateInvoiceService;
use App\Domains\Invoicing\Data\CreateInvoiceData;
use App\Models\Company;
use Tests\TestCase;

class CreateInvoiceServiceTest extends TestCase
{
    private CreateInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CreateInvoiceService::class);
    }

    public function test_can_create_invoice(): void
    {
        $company = Company::factory()->create();

        $data = new CreateInvoiceData(
            customer_id: 1,
            invoice_number: 'INV-001',
            status: 'draft',
            notes: 'Test invoice',
            discount_amount: 0,
            tax_amount: 100,
            total_amount: 1000,
            company_id: $company->id,
        );

        $invoice = $this->service->execute($data);

        $this->assertNotNull($invoice->id);
        $this->assertEquals('INV-001', $invoice->invoice_number);
        $this->assertEquals('draft', $invoice->status);
    }
}
