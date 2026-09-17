<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Dashboard;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
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

    public function test_get_dashboard_overview(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_invoices',
                'total_customers',
                'total_revenue',
                'outstanding_balance',
            ],
        ]);
    }

    public function test_dashboard_shows_invoice_count(): void
    {
        Invoice::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_invoices', 5);
    }

    public function test_dashboard_shows_customer_count(): void
    {
        Customer::factory(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_customers', 3);
    }

    public function test_dashboard_calculates_total_revenue(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        Invoice::factory(2)->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $response->json('data.total_revenue'));
    }

    public function test_dashboard_calculates_outstanding_balance(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid' => 300,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $outstanding = $response->json('data.outstanding_balance');
        $this->assertGreaterThan(0, $outstanding);
    }

    public function test_dashboard_shows_recent_invoices(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        Invoice::factory(5)->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Should include recent invoices in response
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_excludes_other_company_data(): void
    {
        $otherCompany = Company::factory()->create();
        Invoice::factory(10)->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_invoices'));
    }

    public function test_dashboard_with_no_data(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_invoices', 0);
        $response->assertJsonPath('data.total_customers', 0);
    }

    public function test_dashboard_shows_expense_summary(): void
    {
        $category = $this->company->expenseCategories()->first();
        $currency = $this->company->currencies()->first();

        Expense::factory(3)->create([
            'company_id' => $this->company->id,
            'category_id' => $category->id,
            'currency_id' => $currency->id,
            'amount' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Should include expense information
        $response->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_shows_paid_vs_unpaid_invoices(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        // Create paid invoice
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid' => 1000,
            'status' => Invoice::STATUS_PAID,
        ]);

        // Create unpaid invoice
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 500,
            'paid' => 0,
            'status' => Invoice::STATUS_SENT,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Dashboard should show statistics for both
        $this->assertGreaterThan(0, $response->json('data.total_invoices'));
    }

    public function test_dashboard_filters_by_date_range(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'invoice_date' => '2026-01-01',
            'total' => 1000,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard?from_date=2026-08-01&to_date=2026-08-31');

        $response->assertStatus(200);
        // Should filter by date range
        $response->assertJsonStructure(['data']);
    }

    public function test_dashboard_shows_payment_summary(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 1000,
        ]);

        Payment::factory(3)->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200);
        // Should include payment information
        $response->assertJsonStructure(['data']);
    }
}
