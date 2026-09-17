<?php

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Truck;
use App\Models\User;
use App\Services\Document\FleetReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('totals fleet linked expenses by truck', function (): void {
    User::factory()->create(['role' => 'super admin']);
    $company = Company::factory()->create();
    $truck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000]);
    $category = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Fuel']);
    Expense::create(['company_id' => $company->id, 'truck_id' => $truck->id, 'expense_category_id' => $category->id, 'expense_date' => now()->toDateString(), 'amount' => 12500, 'base_amount' => 12500]);
    $report = app(FleetReportService::class)->getSummary($company->id);
    expect($report['total_fleet_expense'])->toBe(12500)->and($report['by_truck'][0]['expense_total'])->toBe(12500);
});
