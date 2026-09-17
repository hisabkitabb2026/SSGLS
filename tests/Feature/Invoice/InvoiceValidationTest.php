<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class InvoiceValidationTest extends TestCase
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

    public function test_invoice_requires_customer_id(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'invoice_number' => 'INV-001',
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_invoice_requires_invoice_number(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice_number']);
    }

    public function test_lorry_receipt_requires_transport_fields(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'template_name' => 'lorry_receipt',
                'invoice_number' => 'DOC-001',
                'invoice_date' => '2026-08-01',
                'customer_id' => $customer->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'truck_no',
            'from_code',
            'to_code',
        ]);
    }

    public function test_lr_receipt_doesnt_require_customer_id(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'template_name' => 'lr_receipt',
                'invoice_number' => 'LR-001',
                'invoice_date' => '2026-08-01',
                'from_code' => 'FROM001',
                'from_name' => 'Bangalore',
                'to_code' => 'TO001',
                'to_name' => 'Delhi',
                'truck_no' => 'GJ05BC1234',
                'basic_freight' => 100,
            ]);

        // Should not require customer_id for LR receipt
        $response->assertStatus(200);
    }

    public function test_invalid_template_name_rejected(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'template_name' => 'invalid_template',
                'customer_id' => $customer->id,
                'invoice_date' => '2026-08-01',
                'invoice_number' => 'INV-001',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['template_name']);
    }

    public function test_duplicate_invoice_number_rejected(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-08-01',
                'invoice_number' => 'INV-001',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 100,
                'total' => 100,
                'tax' => 0,
                'items' => [
                    ['name' => 'Item 1', 'quantity' => 1, 'price' => 100],
                ],
            ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_date' => '2026-08-01',
                'invoice_number' => 'INV-001',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 100,
                'total' => 100,
                'tax' => 0,
                'items' => [
                    ['name' => 'Item 1', 'quantity' => 1, 'price' => 100],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['invoice_number']);
    }
}
