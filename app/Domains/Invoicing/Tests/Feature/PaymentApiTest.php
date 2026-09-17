<?php

namespace Tests\Feature;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\Payment;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_record_payment(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments', [
                'invoice_id' => $invoice->id,
                'amount' => 500,
                'payment_method' => 'bank_transfer',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', 500);
    }

    public function test_can_list_payments(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);
        Payment::factory()->count(2)->create(['invoice_id' => $invoice->id, 'company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
