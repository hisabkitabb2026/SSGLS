<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tax;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\Document\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    private Company $company;

    private Customer $customer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

        $this->company = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->company);

        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company);
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $this->service = app(InvoiceService::class);
    }

    public function test_create_invoice_with_items(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => '2026-08-01',
            'due_date' => '2026-09-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 1000,
            'total' => 1000,
            'tax' => 0,
            'items' => [
                ['name' => 'Item 1', 'quantity' => 2, 'price' => 500],
            ],
        ]);

        $invoice = $this->service->create($request);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals('INV-001', $invoice->invoice_number);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertEquals(1000, $invoice->total);
        $this->assertCount(1, $invoice->items);
    }

    public function test_create_invoice_with_taxes(): void
    {
        $tax = Tax::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-002',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 1000,
            'total' => 1180,
            'tax' => 180,
            'items' => [
                ['name' => 'Taxable Item', 'quantity' => 1, 'price' => 1000],
            ],
            'taxes' => [
                ['tax_id' => $tax->id, 'amount' => 180],
            ],
        ]);

        $invoice = $this->service->create($request);

        $this->assertEquals(1180, $invoice->total);
        $this->assertEquals(180, $invoice->tax);
    }

    public function test_create_invoice_generates_unique_hash(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-003',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);

        $this->assertNotNull($invoice->unique_hash);
        $this->assertNotEmpty($invoice->unique_hash);
    }

    public function test_create_invoice_without_user_provided_number(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);

        // Should auto-generate invoice number
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotEmpty($invoice->invoice_number);
    }

    public function test_create_transport_template_invoice(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'LR-001',
            'invoice_date' => '2026-08-01',
            'template_name' => 'lr_receipt',
            'from_code' => 'FROM001',
            'from_name' => 'Bangalore',
            'to_code' => 'TO001',
            'to_name' => 'Delhi',
            'truck_no' => 'GJ05BC1234',
            'basic_freight' => 100,
        ]);

        $invoice = $this->service->create($request);

        $this->assertEquals('lr_receipt', $invoice->template_name);
        $this->assertEquals('GJ05BC1234', $invoice->truck_no);
    }

    public function test_invoice_has_sequence_number_set(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SEQ-001',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);

        $this->assertNotNull($invoice->sequence_number);
    }

    public function test_create_invoice_with_custom_discount(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-DISC-001',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 10,
            'discount_val' => 100,
            'sub_total' => 1000,
            'total' => 900,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);

        $this->assertEquals(10, $invoice->discount);
        $this->assertEquals(100, $invoice->discount_val);
        $this->assertEquals(900, $invoice->total);
    }

    public function test_create_invoice_with_empty_items_skipped(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-004',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 0,
            'total' => 0,
            'tax' => 0,
            'items' => [
                ['name' => '', 'quantity' => 1, 'price' => 100],
                ['name' => 'Valid Item', 'quantity' => 1, 'price' => 100],
            ],
        ]);

        $invoice = $this->service->create($request);

        // Should only have 1 valid item
        $this->assertCount(1, $invoice->items);
    }

    public function test_create_invoice_with_sent_status(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SENT-001',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'invoiceSend' => true,
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);

        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);
    }

    public function test_create_invoice_preserves_customer_relationship(): void
    {
        $request = Request::create('/api/v1/invoices', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-REL-001',
            'invoice_date' => '2026-08-01',
            'template_name' => 'invoice1',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $invoice = $this->service->create($request);
        $invoice->load('customer');

        $this->assertEquals($this->customer->id, $invoice->customer->id);
        $this->assertEquals($this->customer->name, $invoice->customer->name);
    }
}
