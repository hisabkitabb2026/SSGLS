<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Payment;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class PaymentCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Invoice $invoice;

    private PaymentMethod $paymentMethod;

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
        $this->invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000,
            'paid' => 0,
        ]);
        $this->paymentMethod = PaymentMethod::where('company_id', $this->company->id)->first();
    }

    public function test_get_all_payments(): void
    {
        Payment::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/payments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'customer_id',
                ],
            ],
        ]);
    }

    public function test_get_single_payment(): void
    {
        $payment = Payment::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/payments/'.$payment->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $payment->id);
        $response->assertJsonPath('data.amount', $payment->amount);
    }

    public function test_create_payment_without_invoice(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 500,
                'payment_date' => '2026-08-01',
                'reference_number' => 'PAY-NEW-001',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.amount', 500);
        $this->assertDatabaseHas('payments', ['reference_number' => 'PAY-NEW-001']);
    }

    public function test_create_payment_with_invoice(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $this->invoice->id,
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 500,
                'payment_date' => '2026-08-01',
                'reference_number' => 'PAY-WITH-INV-001',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.invoice_id', $this->invoice->id);
    }

    public function test_create_payment_updates_invoice_paid(): void
    {
        $initialPaid = $this->invoice->paid;

        $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $this->invoice->id,
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 300,
                'payment_date' => '2026-08-01',
                'reference_number' => 'PAY-UPDATE-INV-001',
            ]);

        $this->invoice->refresh();
        $this->assertEquals($initialPaid + 300, $this->invoice->paid);
    }

    public function test_update_payment(): void
    {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'amount' => 500,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/payments/'.$payment->id, [
                'customer_id' => $this->customer->id,
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 750,
                'payment_date' => '2026-08-15',
                'reference_number' => 'PAY-UPDATED-001',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 750]);
    }

    public function test_delete_payment(): void
    {
        $payment = Payment::factory()->create(['company_id' => $this->company->id]);
        $paymentId = $payment->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/payments/'.$paymentId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
    }

    public function test_payments_paginated(): void
    {
        Payment::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/payments?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_payment_can_be_searched_by_reference(): void
    {
        Payment::factory()->create(['company_id' => $this->company->id, 'reference_number' => 'REF-SEARCH-123']);
        Payment::factory()->create(['company_id' => $this->company->id, 'reference_number' => 'REF-OTHER-456']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/payments?search=SEARCH');

        $response->assertStatus(200);
        $foundPayment = collect($response->json('data'))->first(fn ($pmt) => $pmt['reference_number'] === 'REF-SEARCH-123');
        $this->assertNotNull($foundPayment);
    }

    public function test_unauthenticated_user_cannot_get_payments(): void
    {
        $response = $this->getJson('/api/v1/payments');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_payments(): void
    {
        $otherCompany = Company::factory()->create();
        $payment = Payment::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/payments/'.$payment->id);

        $response->assertStatus(403);
    }

    public function test_create_payment_requires_customer_id(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 500,
                'payment_date' => '2026-08-01',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_create_payment_requires_amount(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'payment_method_id' => $this->paymentMethod->id,
                'payment_date' => '2026-08-01',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_create_payment_with_custom_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/payments', [
                'customer_id' => $this->customer->id,
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 200,
                'payment_date' => '2026-08-01',
                'reference_number' => 'PAY-CUSTOM-001',
                'customFields' => [
                    'field1' => 'value1',
                ],
            ]);

        $response->assertStatus(200);
        $paymentId = $response->json('data.id');
        $this->assertDatabaseHas('payments', ['id' => $paymentId]);
    }
}
