<?php

declare(strict_types=1);

namespace Tests\Feature\Company\Expense;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private ExpenseCategory $category;

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

        $this->category = ExpenseCategory::where('company_id', $this->company->id)->first();
    }

    public function test_get_all_expenses(): void
    {
        Expense::factory(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'company_id',
                ],
            ],
        ]);
    }

    public function test_get_single_expense(): void
    {
        $expense = Expense::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses/'.$expense->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $expense->id);
        $response->assertJsonPath('data.amount', $expense->amount);
    }

    public function test_create_expense(): void
    {
        $currency = $this->company->currencies()->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/expenses', [
                'expense_date' => '2026-08-01',
                'amount' => 500,
                'category_id' => $this->category->id,
                'currency_id' => $currency->id,
                'notes' => 'Test expense',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.amount', 500);
        $this->assertDatabaseHas('expenses', ['amount' => 500]);
    }

    public function test_create_expense_with_different_currency(): void
    {
        $companyCurrency = $this->company->currencies()->first();
        $otherCurrency = $this->company->currencies()->where('id', '!=', $companyCurrency->id)->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/expenses', [
                'expense_date' => '2026-08-01',
                'amount' => 750,
                'category_id' => $this->category->id,
                'currency_id' => $otherCurrency->id,
                'notes' => 'Foreign currency expense',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', ['currency_id' => $otherCurrency->id]);
    }

    public function test_update_expense(): void
    {
        $expense = Expense::factory()->create(['company_id' => $this->company->id]);
        $currency = $this->company->currencies()->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->putJson('/api/v1/expenses/'.$expense->id, [
                'expense_date' => '2026-08-15',
                'amount' => 750,
                'category_id' => $this->category->id,
                'currency_id' => $currency->id,
                'notes' => 'Updated expense',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'amount' => 750]);
    }

    public function test_delete_expense(): void
    {
        $expense = Expense::factory()->create(['company_id' => $this->company->id]);
        $expenseId = $expense->id;

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->deleteJson('/api/v1/expenses/'.$expenseId);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_expenses_paginated(): void
    {
        Expense::factory(25)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses?per_page=10');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 10);
    }

    public function test_expense_can_be_filtered_by_category(): void
    {
        $otherCategory = ExpenseCategory::where('company_id', $this->company->id)
            ->where('id', '!=', $this->category->id)
            ->first();

        if (! $otherCategory) {
            $otherCategory = ExpenseCategory::factory()->create(['company_id' => $this->company->id]);
        }

        Expense::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->category->id]);
        Expense::factory()->create(['company_id' => $this->company->id, 'category_id' => $otherCategory->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses?category_id='.$this->category->id);

        $response->assertStatus(200);
        foreach ($response->json('data') as $expense) {
            $this->assertEquals($this->category->id, $expense['category_id']);
        }
    }

    public function test_expense_can_be_filtered_by_date(): void
    {
        Expense::factory()->create(['company_id' => $this->company->id, 'expense_date' => '2026-08-01']);
        Expense::factory()->create(['company_id' => $this->company->id, 'expense_date' => '2026-09-01']);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses?from_date=2026-08-01&to_date=2026-08-31');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_unauthenticated_user_cannot_get_expenses(): void
    {
        $response = $this->getJson('/api/v1/expenses');

        $response->assertStatus(401);
    }

    public function test_user_cannot_access_other_company_expenses(): void
    {
        $otherCompany = Company::factory()->create();
        $expense = Expense::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/expenses/'.$expense->id);

        $response->assertStatus(403);
    }

    public function test_create_expense_requires_amount(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/expenses', [
                'expense_date' => '2026-08-01',
                'category_id' => $this->category->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_create_expense_requires_category(): void
    {
        $currency = $this->company->currencies()->first();

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/expenses', [
                'expense_date' => '2026-08-01',
                'amount' => 500,
                'currency_id' => $currency->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category_id']);
    }

    public function test_delete_multiple_expenses(): void
    {
        $expense1 = Expense::factory()->create(['company_id' => $this->company->id]);
        $expense2 = Expense::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/expenses/bulk-delete', [
                'ids' => [$expense1->id, $expense2->id],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('expenses', ['id' => $expense1->id]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense2->id]);
    }
}
