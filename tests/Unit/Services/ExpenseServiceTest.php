<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Company\CompanyService;
use App\Services\Document\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExpenseService $service;

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

        $this->category = ExpenseCategory::where('company_id', $this->company->id)->first();

        $this->service = app(ExpenseService::class);
    }

    public function test_create_expense_with_basic_info(): void
    {
        $currency = $this->company->currencies()->first();

        $request = Request::create('/api/v1/expenses', 'POST', [
            'expense_date' => '2026-08-01',
            'amount' => 500,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
            'notes' => 'Test expense',
        ]);
        $request->headers->set('company', $this->company->id);

        $expense = $this->service->create($request);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertEquals(500, $expense->amount);
        $this->assertEquals($this->category->id, $expense->category_id);
        $this->assertEquals($this->company->id, $expense->company_id);
    }

    public function test_create_expense_with_company_currency(): void
    {
        $currency = $this->company->currencies()->first();
        $companyCurrency = $currency;

        $request = Request::create('/api/v1/expenses', 'POST', [
            'expense_date' => '2026-08-01',
            'amount' => 750,
            'category_id' => $this->category->id,
            'currency_id' => $companyCurrency->id,
            'company_id' => $this->company->id,
            'notes' => 'Company currency expense',
        ]);
        $request->headers->set('company', $this->company->id);

        $expense = $this->service->create($request);

        $this->assertEquals($companyCurrency->id, $expense->currency_id);
    }

    public function test_create_expense_with_different_currency(): void
    {
        $companyCurrency = $this->company->currencies()->first();
        $otherCurrency = $this->company->currencies()->where('id', '!=', $companyCurrency->id)->first();

        if (! $otherCurrency) {
            $otherCurrency = Currency::factory()->create();
        }

        $request = Request::create('/api/v1/expenses', 'POST', [
            'expense_date' => '2026-08-01',
            'amount' => 1000,
            'category_id' => $this->category->id,
            'currency_id' => $otherCurrency->id,
            'company_id' => $this->company->id,
        ]);
        $request->headers->set('company', $this->company->id);

        $expense = $this->service->create($request);

        $this->assertEquals($otherCurrency->id, $expense->currency_id);
    }

    public function test_update_expense_basic_info(): void
    {
        $currency = $this->company->currencies()->first();
        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'amount' => 500,
        ]);

        $request = Request::create('/api/v1/expenses/'.$expense->id, 'PUT', [
            'expense_date' => '2026-08-15',
            'amount' => 750,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
            'notes' => 'Updated expense',
        ]);
        $request->headers->set('company', $this->company->id);

        $this->service->update($expense, $request);
        $expense->refresh();

        $this->assertEquals(750, $expense->amount);
        $this->assertEquals('Updated expense', $expense->notes);
    }

    public function test_update_expense_with_custom_fields(): void
    {
        $currency = $this->company->currencies()->first();
        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
        ]);

        $request = Request::create('/api/v1/expenses/'.$expense->id, 'PUT', [
            'expense_date' => '2026-08-01',
            'amount' => 500,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
            'customFields' => json_encode(['field1' => 'value1']),
        ]);
        $request->headers->set('company', $this->company->id);

        $this->service->update($expense, $request);
        $expense->load('fields');

        $this->assertNotNull($expense->fields);
    }

    public function test_create_expense_with_custom_fields(): void
    {
        $currency = $this->company->currencies()->first();

        $request = Request::create('/api/v1/expenses', 'POST', [
            'expense_date' => '2026-08-01',
            'amount' => 300,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
            'customFields' => json_encode(['field1' => 'custom_value']),
        ]);
        $request->headers->set('company', $this->company->id);

        $expense = $this->service->create($request);
        $expense->load('fields');

        $this->assertNotNull($expense->fields);
    }

    public function test_update_expense_remove_attachment(): void
    {
        $currency = $this->company->currencies()->first();
        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
        ]);

        $request = Request::create('/api/v1/expenses/'.$expense->id, 'PUT', [
            'expense_date' => '2026-08-01',
            'amount' => 500,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
            'is_attachment_receipt_removed' => true,
        ]);
        $request->headers->set('company', $this->company->id);

        $this->service->update($expense, $request);

        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function test_expense_preserves_category_relationship(): void
    {
        $currency = $this->company->currencies()->first();

        $request = Request::create('/api/v1/expenses', 'POST', [
            'expense_date' => '2026-08-01',
            'amount' => 400,
            'category_id' => $this->category->id,
            'currency_id' => $currency->id,
            'company_id' => $this->company->id,
        ]);
        $request->headers->set('company', $this->company->id);

        $expense = $this->service->create($request);
        $expense->load('category');

        $this->assertEquals($this->category->id, $expense->category->id);
    }

    public function test_create_multiple_expenses(): void
    {
        $currency = $this->company->currencies()->first();

        for ($i = 0; $i < 3; $i++) {
            $request = Request::create('/api/v1/expenses', 'POST', [
                'expense_date' => '2026-08-0'.($i + 1),
                'amount' => 100 * ($i + 1),
                'category_id' => $this->category->id,
                'currency_id' => $currency->id,
                'company_id' => $this->company->id,
            ]);
            $request->headers->set('company', $this->company->id);

            $this->service->create($request);
        }

        $expenses = Expense::where('company_id', $this->company->id)->get();
        $this->assertGreaterThanOrEqual(3, $expenses->count());
    }
}
