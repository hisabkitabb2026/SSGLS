<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Invoice;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class InvoiceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $ownerUser;

    private User $memberUser;

    private User $otherCompanyUser;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

        $this->company = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->company);

        $this->otherCompany = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->otherCompany);

        $this->ownerUser = User::factory()->create();
        $this->ownerUser->companies()->attach($this->company);
        BouncerFacade::scope()->to($this->company->id);
        $this->ownerUser->assign('owner');

        $this->memberUser = User::factory()->create();
        $this->memberUser->companies()->attach($this->company);
        BouncerFacade::scope()->to($this->company->id);
        $this->memberUser->assign('member');

        $this->otherCompanyUser = User::factory()->create();
        $this->otherCompanyUser->companies()->attach($this->otherCompany);
    }

    public function test_owner_can_view_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$invoice->id);

        $response->assertStatus(200);
    }

    public function test_member_can_view_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->memberUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$invoice->id);

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_other_company_invoice(): void
    {
        $otherInvoice = Invoice::factory()->create(['company_id' => $this->otherCompany->id]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices/'.$otherInvoice->id);

        $response->assertStatus(403);
    }

    public function test_owner_can_create_invoice(): void
    {
        $customer = $this->company->customers()->first();
        if (! $customer) {
            $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        }

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-AUTH-001',
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
            ]);

        $response->assertStatus(200);
    }

    public function test_member_can_create_invoice(): void
    {
        $customer = $this->company->customers()->first();
        if (! $customer) {
            $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        }

        $response = $this->actingAs($this->memberUser)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-AUTH-002',
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
            ]);

        $response->assertStatus(200);
    }

    public function test_owner_can_update_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/invoices/'.$invoice->id, [
                'customer_id' => $invoice->customer_id,
                'invoice_number' => 'INV-UPDATED',
                'invoice_date' => '2026-08-15',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
            ]);

        $response->assertStatus(200);
    }

    public function test_member_can_update_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->memberUser)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/invoices/'.$invoice->id, [
                'customer_id' => $invoice->customer_id,
                'invoice_number' => 'INV-MEMBER-UPDATE',
                'invoice_date' => '2026-08-15',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
            ]);

        $response->assertStatus(200);
    }

    public function test_owner_can_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $invoiceId = $invoice->id;

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/invoices/'.$invoiceId);

        $response->assertStatus(200);
    }

    public function test_member_can_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        $invoiceId = $invoice->id;

        $response = $this->actingAs($this->memberUser)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/invoices/'.$invoiceId);

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_create_invoice(): void
    {
        $response = $this->postJson('/api/v1/invoices', [
            'invoice_number' => 'INV-UNAUTH',
            'invoice_date' => '2026-08-01',
        ]);

        $response->assertStatus(401);
    }

    public function test_other_company_user_cannot_create_invoice_in_this_company(): void
    {
        $customer = $this->company->customers()->first();

        $response = $this->actingAs($this->otherCompanyUser)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $customer->id,
                'invoice_number' => 'INV-UNAUTH-COMPANY',
                'invoice_date' => '2026-08-01',
                'template_name' => 'invoice1',
                'discount' => 0,
                'discount_val' => 0,
                'sub_total' => 1000,
                'total' => 1000,
                'tax' => 0,
            ]);

        // Should get 403 or 401
        $this->assertIn($response->status(), [401, 403]);
    }

    public function test_owner_can_list_all_invoices_in_company(): void
    {
        Invoice::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_user_can_only_see_current_company_invoices(): void
    {
        Invoice::factory(5)->create(['company_id' => $this->company->id]);
        Invoice::factory(10)->create(['company_id' => $this->otherCompany->id]);

        $response = $this->actingAs($this->ownerUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200);
        // Should only see invoices from this company
        $invoices = $response->json('data');
        foreach ($invoices as $invoice) {
            $this->assertEquals($this->company->id, $invoice['company_id']);
        }
    }
}
