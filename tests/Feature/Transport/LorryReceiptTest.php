<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Company\CompanyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class LorryReceiptTest extends TestCase
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

    public function test_can_create_lorry_receipt(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $payload = [
            'template_name' => 'lorry_receipt',
            'invoice_number' => 'DOC-001',
            'invoice_date' => '2026-08-01',
            'customer_id' => $customer->id,
            'truck_no' => 'KA-01-AB-1234',
            'from_code' => 'FROM001',
            'from_name' => 'Bangalore',
            'to_code' => 'TO001',
            'to_name' => 'Delhi',
            'mode_of_payment' => 'CASH',
            'gst_tax_payable_by' => 'CONSIGNEE',
            'description_of_goods' => 'Electronics',
            'hsn_code' => '8470',
            'eway_bill_no' => 'EWAY001',
            'actual_weight' => 1000,
            'charged_weight' => 1000,
            'no_of_articles' => 10,
            'packing' => 'BOX',
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'template_name' => 'lorry_receipt',
            'invoice_number' => 'DOC-001',
        ]);
    }

    public function test_can_update_lorry_receipt(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $invoice = Invoice::factory()
            ->for($this->company)
            ->create([
                'template_name' => 'lorry_receipt',
                'customer_id' => $customer->id,
            ]);

        $payload = [
            'template_name' => 'lorry_receipt',
            'invoice_number' => 'DOC-002',
            'invoice_date' => Carbon::parse($invoice->invoice_date)->format('Y-m-d'),
            'truck_no' => 'KA-01-CD-5678',
            'from_code' => 'FROM001',
            'to_code' => 'TO001',
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson("/api/v1/invoices/{$invoice->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'DOC-002',
            'truck_no' => 'KA-01-CD-5678',
        ]);
    }

    public function test_lorry_receipt_requires_transport_fields(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $payload = [
            'template_name' => 'lorry_receipt',
            'invoice_number' => 'DOC-001',
            'invoice_date' => '2026-08-01',
            'customer_id' => $customer->id,
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['truck_no', 'from_code', 'to_code']);
    }

    public function test_can_delete_lorry_receipt(): void
    {
        $invoice = Invoice::factory()
            ->for($this->company)
            ->create(['template_name' => 'lorry_receipt']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertStatus(200);
        $this->assertModelMissing($invoice);
    }

    public function test_can_retrieve_lorry_receipt_with_related_data(): void
    {
        $invoice = Invoice::factory()
            ->for($this->company)
            ->hasItems(3)
            ->create(['template_name' => 'lorry_receipt']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.template_name', 'lorry_receipt')
            ->assertJsonCount(3, 'data.items');
    }

    public function test_lorry_receipt_authorization(): void
    {
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()
            ->for($this->company)
            ->create(['template_name' => 'lorry_receipt']);

        $response = $this->actingAs($otherUser)
            ->withHeader('company', (string) $this->company->id)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'template_name' => 'lorry_receipt',
                'invoice_number' => 'DOC-002',
                'invoice_date' => Carbon::parse($invoice->invoice_date)->format('Y-m-d'),
                'truck_no' => 'KA-01-CD-5678',
                'from_code' => 'FROM001',
                'to_code' => 'TO001',
            ]);

        $response->assertStatus(403);
    }
}
