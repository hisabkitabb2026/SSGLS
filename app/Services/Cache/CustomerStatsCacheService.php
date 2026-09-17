<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Manages caching for Customer statistics to improve performance.
 * Caches expensive monthly and yearly customer calculations.
 */
class CustomerStatsCacheService
{
    private const CACHE_TTL = 3600; // 1 hour for customer stats

    /**
     * Cache key prefix
     */
    private function cacheKey(string $key): string
    {
        return "customer_stats:{$key}";
    }

    /**
     * Company-scoped cache tags
     */
    private function cacheTags(int $companyId): array
    {
        return ['customer_stats', "company:{$companyId}"];
    }

    /**
     * Get customer statistics with monthly breakdown
     */
    public function getStats(Customer $customer, int $companyId, bool $previousYear = false): array
    {
        // Temporarily return empty stats while cache system is being fixed
        return [
            'monthly' => [],
            'yearly' => [],
            'total_invoices' => 0,
            'total_payments' => 0,
            'total_expenses' => 0,
        ];
    }

    /**
     * Calculate customer statistics
     */
    private function calculateStats(Customer $customer, int $companyId, bool $previousYear = false): array
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

        $months = [];
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netProfits = [];
        $monthCounter = 0;

        while ($monthCounter < 12) {
            $invoiceTotal = Invoice::whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany($companyId)
                ->where('customer_id', $customer->id)
                ->sum('base_total');

            $invoiceTotals[] = $invoiceTotal;

            $expenseTotal = Expense::whereBetween(
                'expense_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany($companyId)
                ->where('user_id', $customer->id)
                ->sum('base_amount');

            $expenseTotals[] = $expenseTotal;

            $receiptTotal = Payment::whereBetween(
                'payment_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany($companyId)
                ->where('customer_id', $customer->id)
                ->sum('base_amount');

            $receiptTotals[] = $receiptTotal;
            $netProfits[] = $receiptTotal - $expenseTotal;

            $months[] = $start->translatedFormat('M');
            $monthCounter++;

            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        // Yearly totals
        $salesTotal = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany($companyId)
            ->where('customer_id', $customer->id)
            ->sum('base_total');

        $totalReceipts = Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany($companyId)
            ->where('customer_id', $customer->id)
            ->sum('base_amount');

        $totalExpenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany($companyId)
            ->where('user_id', $customer->id)
            ->sum('base_amount');

        $netProfit = (int) $totalReceipts - (int) $totalExpenses;

        return [
            'months' => $months,
            'invoiceTotals' => $invoiceTotals,
            'expenseTotals' => $expenseTotals,
            'receiptTotals' => $receiptTotals,
            'netProfit' => $netProfit,
            'netProfits' => $netProfits,
            'salesTotal' => $salesTotal,
            'totalReceipts' => $totalReceipts,
            'totalExpenses' => $totalExpenses,
        ];
    }

    /**
     * Invalidate customer stats cache
     */
    public function invalidate(Customer $customer, int $companyId): void
    {
        // Cache will naturally expire after TTL
        // Manual invalidation would require cache driver that supports tagging
    }

    /**
     * Invalidate company customer stats
     */
    public function invalidateCompany(int $companyId): void
    {
        // Cache will naturally expire after TTL
        // Manual invalidation would require cache driver that supports tagging
    }
}
