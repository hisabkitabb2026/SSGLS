<?php

namespace Tests\Feature;

use App\Domains\Expense\Models\Expense;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ExpenseApiTest extends TestCase
{
    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_can_list_expenses(): void
    {
        Expense::factory()->count(3)->create(['company_id' => $this->company->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/expenses');
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_can_create_expense(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/expenses', ['category_id' => 1, 'description' => 'Test', 'amount' => 100, 'date' => now()]);
        $response->assertCreated();
    }
}
