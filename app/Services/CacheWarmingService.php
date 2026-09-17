<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Services\Cache\ConfigurationCacheService;
use App\Services\Cache\CustomerStatsCacheService;
use App\Services\Cache\DashboardCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates cache warming for the application on startup or scheduled intervals.
 * Pre-populates caches to improve initial load times and reduce database pressure.
 */
class CacheWarmingService
{
    public function __construct(
        private readonly DashboardCacheService $dashboardCache,
        private readonly ConfigurationCacheService $configCache,
        private readonly CustomerStatsCacheService $customerStatsCache,
    ) {}

    /**
     * Warm all caches for a specific company or all companies
     *
     * @param  int|null  $companyId  If null, warms cache for all companies
     */
    public function warmAll(?int $companyId = null): array
    {
        try {
            Log::debug('Starting cache warming process', ['companyId' => $companyId]);

            $results = [];

            if ($companyId) {
                $results['dashboard'] = $this->warmDashboardCache($companyId);
                $results['config'] = $this->warmConfigCache($companyId);
                $results['customers'] = $this->warmCustomerCache($companyId);
            } else {
                // Warm cache for all companies
                $companies = Company::query()->pluck('id');

                foreach ($companies as $id) {
                    $results["company_{$id}"] = [
                        'dashboard' => $this->warmDashboardCache($id),
                        'config' => $this->warmConfigCache($id),
                        'customers' => $this->warmCustomerCache($id),
                    ];
                }
            }

            Log::info('Cache warming completed successfully', ['results' => $results]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Cache warming failed', [
                'error' => $e->getMessage(),
                'companyId' => $companyId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm dashboard cache with aggregated metrics and statistics
     *
     * Caches:
     * - Total counts (customers, invoices, estimates, quotations, etc.)
     * - Total amount due across invoices
     * - Recent due invoices
     * - Recent estimates/quotations
     */
    public function warmDashboardCache(int $companyId): array
    {
        try {
            $startTime = microtime(true);

            // Warm dashboard counts
            $this->dashboardCache->getCounts($companyId);

            // Warm total amount due
            $this->dashboardCache->getTotalAmountDue($companyId);

            // Warm recent due invoices (with multiple limits for different views)
            for ($limit = 5; $limit <= 20; $limit += 5) {
                $this->dashboardCache->getRecentDueInvoices($companyId, $limit);
            }

            // Warm recent estimates
            for ($limit = 5; $limit <= 20; $limit += 5) {
                $this->dashboardCache->getRecentEstimates($companyId, $limit);
            }

            // Warm recent quotations
            for ($limit = 5; $limit <= 20; $limit += 5) {
                $this->dashboardCache->getRecentQuotations($companyId, $limit);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('Dashboard cache warmed', [
                'companyId' => $companyId,
                'duration_ms' => $duration,
            ]);

            return [
                'success' => true,
                'duration_ms' => $duration,
                'items_cached' => 6, // counts, total_due, 3x recent items (invoices/estimates/quotations)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warm dashboard cache', [
                'companyId' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm configuration cache with static and semi-static data
     *
     * Caches:
     * - All currencies (global, changes rarely)
     * - Payment methods for the company
     * - Tax types for the company
     *
     * TTL: 7 days (configuration changes infrequently)
     */
    public function warmConfigCache(int $companyId): array
    {
        try {
            $startTime = microtime(true);

            // Warm global currencies cache
            $this->configCache->getCurrencies();

            // Warm company-specific payment methods
            $this->configCache->getPaymentMethods($companyId);

            // Warm company-specific tax types
            $this->configCache->getTaxTypes($companyId);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('Configuration cache warmed', [
                'companyId' => $companyId,
                'duration_ms' => $duration,
            ]);

            return [
                'success' => true,
                'duration_ms' => $duration,
                'items_cached' => 3, // currencies, payment_methods, tax_types
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warm configuration cache', [
                'companyId' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm customer cache with active customer data and statistics
     *
     * Caches:
     * - All active customers for the company
     * - Customer statistics and historical data for each active customer
     *
     * TTL: 1 hour (customer data changes frequently)
     */
    public function warmCustomerCache(int $companyId): array
    {
        try {
            $startTime = microtime(true);
            $cachedCount = 0;

            // Get all active customers for the company
            $customers = Customer::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->limit(500) // Prevent warming too many customers at once
                ->get(['id', 'company_id']);

            // Warm individual customer statistics
            foreach ($customers as $customer) {
                // Cache current year statistics
                $this->customerStatsCache->getStats($customer, $companyId, false);

                // Cache previous year statistics for comparison
                $this->customerStatsCache->getStats($customer, $companyId, true);

                $cachedCount++;
            }

            // Cache the complete active customer list
            $cacheKey = "customers:company:{$companyId}:active";
            Cache::remember($cacheKey, 3600, function () use ($companyId) {
                return Customer::query()
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->with('billingAddress', 'shippingAddress')
                    ->get();
            });

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('Customer cache warmed', [
                'companyId' => $companyId,
                'customers_cached' => $cachedCount,
                'duration_ms' => $duration,
            ]);

            return [
                'success' => true,
                'duration_ms' => $duration,
                'items_cached' => $cachedCount,
                'total_statistics_items' => $cachedCount * 2, // current + previous year
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warm customer cache', [
                'companyId' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear all warmed caches for a specific company
     */
    public function clearCompanyCache(int $companyId): bool
    {
        try {
            // Invalidate dashboard cache
            Cache::tags(['dashboard', "company:{$companyId}"])->flush();

            // Invalidate configuration cache
            $this->configCache->invalidate($companyId);

            // Invalidate customer stats cache
            Cache::tags(['customer_stats', "company:{$companyId}"])->flush();

            // Clear active customers list
            Cache::forget("customers:company:{$companyId}:active");

            Log::info('Company cache cleared', ['companyId' => $companyId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear company cache', [
                'companyId' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear all warmed caches across the application
     */
    public function clearAllCaches(): bool
    {
        try {
            // Clear all tagged caches
            Cache::tags(['dashboard', 'customer_stats', 'config'])->flush();

            // Clear global configuration cache
            $this->configCache->invalidateAll();

            // Clear all customer caches
            Cache::tags(['customers'])->flush();

            Log::info('All application caches cleared');

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear all caches', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
