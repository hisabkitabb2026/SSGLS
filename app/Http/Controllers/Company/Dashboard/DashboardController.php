<?php

namespace App\Http\Controllers\Company\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Cache\DashboardCacheService;
use App\Services\Report\ProfitLossCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProfitLossCalculationService $profitLossService,
        private readonly DashboardCacheService $cacheService,
    ) {}

    /**
     * Handle the incoming request.
     * Uses cache service to prevent expensive repeated calculations.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));

        if (! $company) {
            return response()->json(['error' => 'Company not found'], 400);
        }

        $companyId = $company->id;

        $this->authorize('view dashboard', $company);

        $previousYear = $request->has('previous_year');

        // Get cached counts (customers, invoices, estimates)
        $counts = $this->cacheService->getCounts($companyId);

        // Get cached amount due
        $total_amount_due = $this->cacheService->getTotalAmountDue($companyId);

        // Get cached monthly summary
        $chart_data = $this->cacheService->getMonthlySummary($companyId, $previousYear);

        // Get cached yearly totals
        $yearly = $this->cacheService->getYearlyTotals($companyId, $previousYear);

        // Get recent invoices and estimates (with eager loaded customer)
        $recent_due_invoices = $this->cacheService->getRecentDueInvoices($companyId);
        $recent_estimates = $this->cacheService->getRecentEstimates($companyId);

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $counts['total_customer_count'],
            'total_invoice_count' => $counts['total_invoice_count'],
            'total_invoice_receipt_count' => $counts['total_invoice_receipt_count'],
            'total_estimate_count' => $counts['total_estimate_count'],
            'total_quotation_count' => $counts['total_quotation_count'],
            'total_lr_receipt_count' => $counts['total_lr_receipt_count'],
            'total_lorry_receipt_count' => $counts['total_lorry_receipt_count'],

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,

            'total_sales' => $yearly['total_sales'],
            'total_receipts' => $yearly['total_receipts'],
            'total_expenses' => $yearly['total_expenses'],
            'total_net_income' => $yearly['total_net_income'],
        ]);
    }
}
