<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_get_all_invoices(): void
    {
        Invoice::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'customer_id',
                    'total',
                    'status',
                ],
            ],
        ]);
    }

    public function test_get_single_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$invoice->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $invoice->id);
        $response->assertJsonPath('data.invoice_number', $invoice->invoice_number);
    }

    public function test_create_invoice(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $this->customer->id,
                'invoice_number' => 'INV-NEW-001',
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
                'items' => [
                    ['name' => 'Item 1', 'quantity' => 1, 'price' => 1000],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.invoice_number', 'INV-NEW-001');
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-NEW-001']);
    }

    public function test_update_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/invoices/'.$invoice->id, [
                'customer_id' => $invoice->customer_id,
                'invoice_number' => 'INV-UPDATED-001',
                'invoice_date' => '2026-08-15',
                'template_name' => 'invoice1',
                'discount' => 5,
                'discount_val' => 50,
                'sub_total' => 950,
                'total' => 950,
                'tax' => 0,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'invoice_number' => 'INV-UPDATED-001']);
    }

    public function test_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $invoiceId = $invoice->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/invoices/'.$invoiceId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('invoices', ['id' => $invoiceId]);
    }

    public function test_get_invoice_with_items(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $invoice->items()->createMany([
            ['name' => 'Item 1', 'quantity' => 2, 'price' => 100],
            ['name' => 'Item 2', 'quantity' => 1, 'price' => 200],
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$invoice->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.items.0.name', 'Item 1');
        $response->assertJsonPath('data.items.1.name', 'Item 2');
    }

    public function test_get_invoices_paginated(): void
    {
        Invoice::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_unauthenticated_user_cannot_get_invoices(): void
    {
        $response = $this->getJson('/api/v1/invoices');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_invoices(): void
    {
        $otherCompany = Company::factory()->create();
        $invoice = Invoice::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$invoice->id);

        $response->assertStatus(403);
    }

    public function test_invoice_can_be_filtered_by_status(): void
    {
        Invoice::factory()->create(['company_id' => $this->company->id, 'status' => Invoice::STATUS_DRAFT]);
        Invoice::factory()->create(['company_id' => $this->company->id, 'status' => Invoice::STATUS_SENT]);
        Invoice::factory()->create(['company_id' => $this->company->id, 'status' => Invoice::STATUS_VIEWED]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices?status='.Invoice::STATUS_SENT);

        $response->assertStatus(200);
        // All items in data should have status SENT
        foreach ($response->json('data') as $invoice) {
            $this->assertEquals(Invoice::STATUS_SENT, $invoice['status']);
        }
    }

    public function test_invoice_can_be_searched_by_number(): void
    {
        Invoice::factory()->create(['company_id' => $this->company->id, 'invoice_number' => 'INV-SEARCH-123']);
        Invoice::factory()->create(['company_id' => $this->company->id, 'invoice_number' => 'INV-OTHER-456']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices?search=SEARCH');

        $response->assertStatus(200);
        // Should contain the searched invoice
        $foundInvoice = collect($response->json('data'))->first(fn ($inv) => $inv['invoice_number'] === 'INV-SEARCH-123');
        $this->assertNotNull($foundInvoice);
    }
}
