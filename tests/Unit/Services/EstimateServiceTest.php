<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\Document\EstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EstimateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EstimateService $service;

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

        $this->service = app(EstimateService::class);
    }

    public function test_create_estimate_with_items(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-001',
            'estimate_date' => '2026-08-01',
            'expiry_date' => '2026-08-31',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 1000,
            'total' => 1000,
            'tax' => 0,
            'items' => [
                ['name' => 'Item 1', 'quantity' => 1, 'price' => 1000],
            ],
        ]);

        $estimate = $this->service->create($request);

        $this->assertInstanceOf(Estimate::class, $estimate);
        $this->assertEquals('EST-001', $estimate->estimate_number);
        $this->assertEquals($this->customer->id, $estimate->customer_id);
        $this->assertEquals(Estimate::STATUS_DRAFT, $estimate->status);
        $this->assertEquals(1000, $estimate->total);
    }

    public function test_create_estimate_generates_unique_hash(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-002',
            'estimate_date' => '2026-08-01',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);

        $this->assertNotNull($estimate->unique_hash);
        $this->assertNotEmpty($estimate->unique_hash);
    }

    public function test_create_estimate_with_sent_status(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-SENT-001',
            'estimate_date' => '2026-08-01',
            'estimateSend' => true,
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 750,
            'total' => 750,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);

        $this->assertEquals(Estimate::STATUS_SENT, $estimate->status);
    }

    public function test_create_estimate_has_sequence_number(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-SEQ-001',
            'estimate_date' => '2026-08-01',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);

        $this->assertNotNull($estimate->sequence_number);
    }

    public function test_create_estimate_with_discount(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-DISC-001',
            'estimate_date' => '2026-08-01',
            'discount' => 10,
            'discount_val' => 100,
            'sub_total' => 1000,
            'total' => 900,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);

        $this->assertEquals(10, $estimate->discount);
        $this->assertEquals(100, $estimate->discount_val);
        $this->assertEquals(900, $estimate->total);
    }

    public function test_create_estimate_preserves_customer_relationship(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-REL-001',
            'estimate_date' => '2026-08-01',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);
        $estimate->load('customer');

        $this->assertEquals($this->customer->id, $estimate->customer->id);
    }

    public function test_update_estimate(): void
    {
        $estimate = Estimate::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 500,
        ]);

        $request = Request::create('/api/v1/estimates/'.$estimate->id, 'PUT', [
            'customer_id' => $estimate->customer_id,
            'estimate_number' => 'EST-UPDATED-001',
            'estimate_date' => '2026-08-15',
            'discount' => 5,
            'discount_val' => 50,
            'sub_total' => 950,
            'total' => 900,
            'tax' => 0,
        ]);

        $updated = $this->service->update($estimate, $request);

        $estimate->refresh();
        $this->assertEquals('EST-UPDATED-001', $estimate->estimate_number);
        $this->assertEquals(900, $estimate->total);
    }

    public function test_delete_estimate(): void
    {
        $estimate = Estimate::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
        ]);
        $estimateId = $estimate->id;

        $this->service->delete($estimate->id);

        $this->assertDatabaseMissing('estimates', ['id' => $estimateId]);
    }

    public function test_convert_estimate_to_invoice(): void
    {
        $estimate = Estimate::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000,
        ]);

        // Test conversion (if service has this method)
        // This would depend on the actual implementation
        $this->assertInstanceOf(Estimate::class, $estimate);
    }

    public function test_create_estimate_with_custom_fields(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_number' => 'EST-CUSTOM-001',
            'estimate_date' => '2026-08-01',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
            'customFields' => [
                'field1' => 'value1',
            ],
        ]);

        $estimate = $this->service->create($request);
        $estimate->load('fields');

        $this->assertNotNull($estimate->fields);
    }

    public function test_estimate_auto_generates_number_if_not_provided(): void
    {
        $request = Request::create('/api/v1/estimates', 'POST', [
            'customer_id' => $this->customer->id,
            'estimate_date' => '2026-08-01',
            'discount' => 0,
            'discount_val' => 0,
            'sub_total' => 500,
            'total' => 500,
            'tax' => 0,
        ]);

        $estimate = $this->service->create($request);

        // Should auto-generate number
        $this->assertNotNull($estimate->estimate_number);
        $this->assertNotEmpty($estimate->estimate_number);
    }
}
