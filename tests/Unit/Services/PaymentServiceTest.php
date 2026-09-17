<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\Document\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    private Company $company;

    private Customer $customer;

    private Invoice $invoice;

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
        $this->invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000,
            'paid' => 0,
        ]);

        $this->service = app(PaymentService::class);
    }

    public function test_create_payment_without_invoice(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 500,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-001',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals($this->customer->id, $payment->customer_id);
        $this->assertNull($payment->invoice_id);
    }

    public function test_create_payment_with_invoice(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 500,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-002',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);

        $this->assertEquals(500, $payment->amount);
        $this->assertEquals($this->invoice->id, $payment->invoice_id);
    }

    public function test_create_payment_updates_invoice_paid_amount(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();
        $initialPaid = $this->invoice->paid;

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 300,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-003',
            'company_id' => $this->company->id,
        ]);

        $this->service->create($request);
        $this->invoice->refresh();

        $this->assertEquals($initialPaid + 300, $this->invoice->paid);
    }

    public function test_create_payment_generates_unique_hash(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 250,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-004',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);

        $this->assertNotNull($payment->unique_hash);
        $this->assertNotEmpty($payment->unique_hash);
    }

    public function test_create_payment_sets_sequence_numbers(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 200,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-005',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);

        $this->assertNotNull($payment->sequence_number);
    }

    public function test_update_payment_with_same_invoice(): void
    {
        $payment = Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 300,
            'company_id' => $this->company->id,
        ]);
        $initialPaid = $this->invoice->paid;

        $request = Request::create('/api/v1/payments/'.$payment->id, 'PUT', [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 400,
            'payment_date' => '2026-08-01',
            'company_id' => $this->company->id,
        ]);

        $this->service->update($payment, $request);
        $this->invoice->refresh();

        // Should have adjusted: remove old 300, add new 400
        $this->assertEquals($initialPaid + 100, $this->invoice->paid);
    }

    public function test_update_payment_change_invoice(): void
    {
        $payment = Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 200,
            'company_id' => $this->company->id,
        ]);

        $newInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000,
            'paid' => 0,
        ]);

        $request = Request::create('/api/v1/payments/'.$payment->id, 'PUT', [
            'customer_id' => $this->customer->id,
            'invoice_id' => $newInvoice->id,
            'amount' => 200,
            'payment_date' => '2026-08-01',
            'company_id' => $this->company->id,
        ]);

        $this->service->update($payment, $request);

        $this->invoice->refresh();
        $newInvoice->refresh();

        $this->assertLessThan($payment->amount, $this->invoice->paid);
        $this->assertGreaterThan(0, $newInvoice->paid);
    }

    public function test_create_payment_with_custom_fields(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 150,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-006',
            'company_id' => $this->company->id,
            'customFields' => [
                'field1' => 'value1',
            ],
        ]);

        $payment = $this->service->create($request);
        $payment->load('fields');

        $this->assertNotNull($payment->fields);
    }

    public function test_payment_preserves_customer_relationship(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-007',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);
        $payment->load('customer');

        $this->assertEquals($this->customer->id, $payment->customer->id);
    }

    public function test_payment_preserves_invoice_relationship(): void
    {
        $paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();

        $request = Request::create('/api/v1/payments', 'POST', [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100,
            'payment_date' => '2026-08-01',
            'reference_number' => 'PAY-008',
            'company_id' => $this->company->id,
        ]);

        $payment = $this->service->create($request);
        $payment->load('invoice');

        $this->assertEquals($this->invoice->id, $payment->invoice->id);
    }
}
