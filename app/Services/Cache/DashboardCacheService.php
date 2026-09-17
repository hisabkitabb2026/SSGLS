<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Manages caching for Dashboard data to improve performance.
 * Caches expensive aggregation queries and statistical calculations.
 */
class DashboardCacheService
{
    private const CACHE_TTL = 3600; // 1 hour — invalidation handles freshness; TTL is a safety net

    /**
     * Pre-warmed limits for recent invoices/estimates.
     * Used by both CacheWarmingService and invalidate() to stay in sync.
     */
    private const PRE_WARMED_LIMITS = [5, 10, 15, 20];

    /**
     * Cache key prefix
     */
    private function cacheKey(string $key): string
    {
        return "dashboard:{$key}";
    }

    /**
     * Get dashboard counts (total customers, invoices, estimates, etc.)
     */
    public function getCounts(int $companyId): array
    {
        $key = $this->cacheKey("company:{$companyId}:counts");

        return Cache::remember($key, self::CACHE_TTL, function () use ($companyId) {
            return [
                'total_customer_count' => Customer::whereCompany($companyId)->count(),
                'total_invoice_count' => Invoice::whereCompany($companyId)
                    ->whereNotIn('template_name', ['lr_receipt', 'lorry_receipt'])
                    ->count(),
                'total_invoice_receipt_count' => Invoice::whereCompany($companyId)
                    ->where('template_name', 'office_invoice')
                    ->count(),
                'total_estimate_count' => Estimate::whereCompany($companyId)
                    ->where('estimate_type', 'estimate')
                    ->count(),
                'total_quotation_count' => Estimate::whereCompany($companyId)
                    ->where('estimate_type', 'quotation')
                    ->count(),
                'total_lr_receipt_count' => Invoice::whereCompany($companyId)
                    ->where('template_name', 'lr_receipt')
                    ->count(),
                'total_lorry_receipt_count' => Invoice::whereCompany($companyId)
                    ->where('template_name', 'lorry_receipt')
                    ->count(),
            ];
        });
    }

    /**
     * Get total amount due across all invoices
     */
    public function getTotalAmountDue(int $companyId): int
    {
        $key = $this->cacheKey("company:{$companyId}:total_amount_due");

        return Cache::remember($key, self::CACHE_TTL, function () use ($companyId) {
            // Only count customer-facing invoices (office_invoice), not internal
            // transport documents (lr_receipt, lorry_receipt) which have their
            // own due amounts that are not customer receivables.
            return (int) Invoice::whereCompany($companyId)
                ->where('template_name', 'office_invoice')
                ->sum('base_due_amount');
        });
    }

    /**
     * Get recent due invoices with customer data
     */
    public function getRecentDueInvoices(int $companyId, int $limit = 5)
    {
        $key = $this->cacheKey("company:{$companyId}:recent_due_invoices:{$limit}");

        return Cache::remember($key, self::CACHE_TTL, function () use ($companyId, $limit) {
            // Only show customer-facing invoices (office_invoice), not internal
            // transport documents (lr_receipt, lorry_receipt).
            return Invoice::with('customer')
                ->whereCompany($companyId)
                ->where('template_name', 'office_invoice')
                ->where('base_due_amount', '>', 0)
                ->take($limit)
                ->latest()
                ->get();
        });
    }

    /**
     * Get recent estimates with customer data
     * Filters by estimate_type = 'estimate' (excludes quotations)
     */
    public function getRecentEstimates(int $companyId, int $limit = 5)
    {
        $key = $this->cacheKey("company:{$companyId}:recent_estimates:{$limit}");

        return Cache::remember($key, self::CACHE_TTL, function () use ($companyId, $limit) {
            return Estimate::with('customer')
                ->whereCompany($companyId)
                ->where('estimate_type', 'estimate')
                ->take($limit)
                ->latest()
                ->get();
        });
    }

    /**
     * Get monthly financial summary (invoices, expenses, etc.)
     */
    public function getMonthlySummary(int $companyId, bool $previousYear = false): array
    {
        $yearSuffix = $previousYear ? ':previous_year' : ':current_year';
        $key = $this->cacheKey("company:{$companyId}:monthly_summary{$yearSuffix}");

        return Cache::remember($key, self::CACHE_TTL, function () use ($companyId, $previousYear) {
            return $this->calculateMonthlySummary($companyId, $previousYear);
        });
    }

    /**
     * Get yearly totals (sales, receipts, expenses, net income)
     *
     * NOTE: Intentionally NOT cached. Sales must always reflect the latest
     * invoice/invoice-receipt entries. Caching caused stale data for up to 24h.
     */
    public function getYearlyTotals(int $companyId, bool $previousYear = false): array
    {
        return $this->calculateYearlyTotals($companyId, $previousYear);
    }

    /**
     * Calculate monthly summary data
     * Handles conditional logic for Sales (Invoice vs Invoice Receipt based on enabled settings)
     */
    private function calculateMonthlySummary(int $companyId, bool $previousYear = false): array
    {
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $companyId);
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = (int) $terms[0];

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
            $end->month($companyStartMonth)->endOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
            $end->subYear()->month($companyStartMonth)->endOfMonth();
        }

        if ($previousYear) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        $enableStandardInvoices = CompanySetting::getSetting('enable_standard_invoices', $companyId) === 'YES';
        $enableInvoiceReceipts = CompanySetting::getSetting('enable_invoice_receipts', $companyId) === 'YES';

        $invoice_totals = [];
        $receipt_totals = [];
        $expense_totals = [];
        $net_income_totals = [];
        $months = [];
        $monthCounter = 0;

        while ($monthCounter < 12) {
            // Sales: Conditional based on enabled document type
            $salesQuery = Invoice::whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany($companyId);

            // Standard invoices branch disabled — Sales always uses office_invoice
            // (the Invoice Receipt template) for transport/logistics operations.
            // if ($enableStandardInvoices) {
            //     $salesQuery->where(function ($q) {
            //         $q->whereNull('template_name')->orWhere('template_name', 'invoice');
            //     });
            // } elseif
            if ($enableInvoiceReceipts) {
                // Include only invoice receipts (office_invoice template)
                $salesQuery->where('template_name', 'office_invoice');
            } else {
                // Neither enabled, return 0
                $salesQuery->whereRaw('1=0');
            }

            $invoiceTotal = $salesQuery->sum('base_total') ?? 0;
            $invoice_totals[] = $invoiceTotal;

            // LR PROFIT: Only calculated when Invoice Receipts are enabled.
            // Formula: SUM(amount_credit) - SUM(amount_debit) from LR Receipts and Lorry Receipts
            if ($enableInvoiceReceipts) {
                $lrAmount = Invoice::whereIn('template_name', ['lr_receipt', 'lorry_receipt'])
                    ->whereCompany($companyId)
                    ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                    ->selectRaw('COALESCE(SUM(amount_credit), 0) - COALESCE(SUM(amount_debit), 0) as net_amount')
                    ->value('net_amount') ?? 0;
            } else {
                $lrAmount = 0;
            }

            $receipt_totals[] = $lrAmount;

            // EXPENSES: Sum of all expenses
            $expenses = Expense::whereBetween(
                'expense_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany($companyId)
                ->sum('base_amount') ?? 0;

            $expense_totals[] = $expenses;
            $net_income_totals[] = (int) $lrAmount - (int) $expenses;

            $months[] = $start->translatedFormat('M');

            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        return [
            'months' => $months,
            'invoice_totals' => $invoice_totals,
            'expense_totals' => $expense_totals,
            'receipt_totals' => $receipt_totals,
            'net_income_totals' => $net_income_totals,
        ];
    }

    /**
     * Calculate yearly totals
     * Handles conditional logic for:
     * - Sales: Invoice (if enabled) OR Invoice Receipt (if Invoice disabled)
     * - LR Profit: LR Receipt (amount_credit - amount_debit)
     * - Expenses: Sum of all expenses
     * - Net Income: LR Profit - Expenses
     */
    private function calculateYearlyTotals(int $companyId, bool $previousYear = false): array
    {
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $companyId);
        $startDate = Carbon::now();
        $start = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = (int) $terms[0];

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
        }

        if ($previousYear) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
        }

        // Move to end of fiscal year
        $end = clone $start;
        $end->addYear()->subDay()->endOfMonth();

        // SALES: Conditional based on enabled document type
        // If Invoice is enabled → use Invoice (template_name NULL or 'invoice')
        // Else if Invoice Receipt is enabled → use Invoice Receipt (office_invoice template)
        $enableStandardInvoices = CompanySetting::getSetting('enable_standard_invoices', $companyId) === 'YES';
        $enableInvoiceReceipts = CompanySetting::getSetting('enable_invoice_receipts', $companyId) === 'YES';

        $salesQuery = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $end->format('Y-m-d')]
        )
            ->whereCompany($companyId);

        // Standard invoices branch disabled — Sales always uses office_invoice
        // (the Invoice Receipt template) for transport/logistics operations.
        // if ($enableStandardInvoices) {
        //     $salesQuery->where(function ($q) {
        //         $q->whereNull('template_name')->orWhere('template_name', 'invoice');
        //     });
        // } elseif
        if ($enableInvoiceReceipts) {
            // Include only invoice receipts (office_invoice template)
            $salesQuery->where('template_name', 'office_invoice');
        } else {
            // Neither enabled, return 0
            $salesQuery->whereRaw('1=0');
        }

        $total_sales = $salesQuery->sum('base_total') ?? 0;

        // LR PROFIT: Only calculated when Invoice Receipts are enabled.
        // Formula: SUM(amount_credit) - SUM(amount_debit) from LR Receipts and Lorry Receipts
        if ($enableInvoiceReceipts) {
            $total_receipts = Invoice::whereIn('template_name', ['lr_receipt', 'lorry_receipt'])
                ->whereCompany($companyId)
                ->whereBetween('invoice_date', [$startDate->format('Y-m-d'), $end->format('Y-m-d')])
                ->selectRaw('COALESCE(SUM(amount_credit), 0) - COALESCE(SUM(amount_debit), 0) as net_amount')
                ->value('net_amount') ?? 0;
        } else {
            $total_receipts = 0;
        }

        // EXPENSES: Sum of all expenses
        $total_expenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $end->format('Y-m-d')]
        )
            ->whereCompany($companyId)
            ->sum('base_amount') ?? 0;

        // NET INCOME: LR Profit - Expenses
        $total_net_income = (int) $total_receipts - (int) $total_expenses;

        return [
            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
        ];
    }

    /**
     * Invalidate dashboard cache on invoice/expense changes
     *
     * NOTE: yearly_totals is intentionally not cached (computed fresh every
     * request) so it is not listed here. monthly_summary is still cached
     * because it runs 12 iterations of queries per call.
     */
    public function invalidate(int $companyId): void
    {
        Cache::forget($this->cacheKey("company:{$companyId}:counts"));
        Cache::forget($this->cacheKey("company:{$companyId}:total_amount_due"));

        // Invalidate all pre-warmed limits for recent invoices/estimates
        // (CacheWarmingService pre-populates limits 5, 10, 15, 20)
        foreach (self::PRE_WARMED_LIMITS as $limit) {
            Cache::forget($this->cacheKey("company:{$companyId}:recent_due_invoices:{$limit}"));
            Cache::forget($this->cacheKey("company:{$companyId}:recent_estimates:{$limit}"));
        }

        Cache::forget($this->cacheKey("company:{$companyId}:monthly_summary:current_year"));
        Cache::forget($this->cacheKey("company:{$companyId}:monthly_summary:previous_year"));
    }

    /**
     * Invalidate specific cache entries
     */
    public function invalidateKeys(int $companyId, array $keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($this->cacheKey($key));
        }
    }
}
