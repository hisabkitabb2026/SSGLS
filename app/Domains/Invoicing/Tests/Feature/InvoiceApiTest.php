<?php

namespace Tests\Feature;

use App\Domains\Invoicing\Models\Invoice;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_invoices(): void
    {
        Invoice::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_invoice(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/invoices', [
                'customer_id' => 1,
                'invoice_number' => 'INV-2024-001',
                'total_amount' => 1000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice_number', 'INV-2024-001');
    }

    public function test_can_show_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }

    public function test_can_update_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/invoices/{$invoice->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertOk();
    }

    public function test_can_delete_invoice(): void
    {
        $invoice = Invoice::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertNoContent();
    }
}
