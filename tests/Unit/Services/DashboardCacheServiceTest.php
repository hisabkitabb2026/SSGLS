<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Cache\DashboardCacheService;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardCacheService $service;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

        $this->company = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->company);

        $this->service = app(DashboardCacheService::class);
        Cache::flush();
    }

    public function test_get_counts_returns_correct_customer_count(): void
    {
        Customer::factory(5)->create(['company_id' => $this->company->id]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(5, $counts['total_customer_count']);
    }

    public function test_get_counts_returns_correct_invoice_count(): void
    {
        Invoice::factory(3)->create([
            'company_id' => $this->company->id,
            'template_name' => 'invoice1',
        ]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(3, $counts['total_invoice_count']);
    }

    public function test_get_counts_returns_correct_estimate_count(): void
    {
        Estimate::factory(2)->create(['company_id' => $this->company->id]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(2, $counts['total_estimate_count']);
    }

    public function test_get_counts_excludes_lr_receipts_from_invoice_count(): void
    {
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'template_name' => 'invoice1',
        ]);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'template_name' => 'lr_receipt',
        ]);

        $counts = $this->service->getCounts($this->company->id);

        // Should only count standard invoices, not lr_receipt
        $this->assertEquals(1, $counts['total_invoice_count']);
    }

    public function test_get_counts_tracks_lr_receipts_separately(): void
    {
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'template_name' => 'lr_receipt',
        ]);
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'template_name' => 'lr_receipt',
        ]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(2, $counts['total_lr_receipt_count']);
    }

    public function test_get_counts_tracks_lorry_receipts_separately(): void
    {
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'template_name' => 'lorry_receipt',
        ]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(1, $counts['total_lorry_receipt_count']);
    }

    public function test_get_counts_caches_results(): void
    {
        Customer::factory(3)->create(['company_id' => $this->company->id]);

        // First call
        $counts1 = $this->service->getCounts($this->company->id);

        // Add more customers
        Customer::factory(2)->create(['company_id' => $this->company->id]);

        // Second call should return cached value (still 3)
        $counts2 = $this->service->getCounts($this->company->id);

        $this->assertEquals($counts1['total_customer_count'], $counts2['total_customer_count']);
    }

    public function test_get_total_amount_due(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid' => 300,
            'base_due_amount' => 700,
        ]);

        $amountDue = $this->service->getTotalAmountDue($this->company->id);

        $this->assertGreaterThan(0, $amountDue);
    }

    public function test_get_total_amount_due_excludes_paid_invoices(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        // Fully paid invoice
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 1000,
            'paid' => 1000,
            'base_due_amount' => 0,
        ]);

        // Unpaid invoice
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'total' => 500,
            'paid' => 0,
            'base_due_amount' => 500,
        ]);

        $amountDue = $this->service->getTotalAmountDue($this->company->id);

        $this->assertEquals(500, $amountDue);
    }

    public function test_get_counts_excludes_other_companies(): void
    {
        $otherCompany = Company::factory()->create();

        Customer::factory(5)->create(['company_id' => $this->company->id]);
        Customer::factory(10)->create(['company_id' => $otherCompany->id]);

        $counts = $this->service->getCounts($this->company->id);

        $this->assertEquals(5, $counts['total_customer_count']);
    }

    public function test_counts_structure_is_complete(): void
    {
        $counts = $this->service->getCounts($this->company->id);

        $this->assertArrayHasKey('total_customer_count', $counts);
        $this->assertArrayHasKey('total_invoice_count', $counts);
        $this->assertArrayHasKey('total_estimate_count', $counts);
        $this->assertArrayHasKey('total_lr_receipt_count', $counts);
        $this->assertArrayHasKey('total_lorry_receipt_count', $counts);
    }

    public function test_get_counts_returns_zero_for_empty_company(): void
    {
        $emptyCompany = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($emptyCompany);

        $counts = $this->service->getCounts($emptyCompany->id);

        $this->assertEquals(0, $counts['total_customer_count']);
        $this->assertEquals(0, $counts['total_invoice_count']);
    }

    public function test_cache_is_tagged_by_company(): void
    {
        $otherCompany = Company::factory()->create();

        Customer::factory(3)->create(['company_id' => $this->company->id]);
        Customer::factory(5)->create(['company_id' => $otherCompany->id]);

        $counts1 = $this->service->getCounts($this->company->id);
        $counts2 = $this->service->getCounts($otherCompany->id);

        $this->assertEquals(3, $counts1['total_customer_count']);
        $this->assertEquals(5, $counts2['total_customer_count']);
    }
}
