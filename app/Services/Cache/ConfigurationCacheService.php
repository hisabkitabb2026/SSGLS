<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\TaxType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Manages caching for configuration data (currencies, payment methods, tax types).
 * These are relatively static and can be cached for long periods.
 */
class ConfigurationCacheService
{
    private const CACHE_TTL = 86400 * 7; // 1 week for config data

    /**
     * Cache key prefix
     */
    private function cacheKey(string $key): string
    {
        return "config:{$key}";
    }

    /**
     * Company-scoped cache tags
     */
    private function cacheTags(int $companyId): array
    {
        return ['config', "company:{$companyId}"];
    }

    /**
     * Get all currencies (cached)
     */
    public function getCurrencies(): Collection
    {
        $key = $this->cacheKey('currencies');

        return Cache::remember($key, self::CACHE_TTL, function () {
            return Currency::all();
        });
    }

    /**
     * Get all payment methods for company
     */
    public function getPaymentMethods(int $companyId)
    {
        $key = $this->cacheKey("company:{$companyId}:payment_methods");

        return Cache::tags($this->cacheTags($companyId))
            ->remember($key, self::CACHE_TTL, function () use ($companyId) {
                return PaymentMethod::where('company_id', $companyId)
                    ->active()
                    ->get();
            });
    }

    /**
     * Get all tax types for company
     */
    public function getTaxTypes(int $companyId)
    {
        $key = $this->cacheKey("company:{$companyId}:tax_types");

        return Cache::tags($this->cacheTags($companyId))
            ->remember($key, self::CACHE_TTL, function () use ($companyId) {
                return TaxType::where('company_id', $companyId)
                    ->active()
                    ->get();
            });
    }

    /**
     * Invalidate configuration cache when settings change
     */
    public function invalidate(int $companyId): void
    {
        Cache::tags($this->cacheTags($companyId))->flush();
    }

    /**
     * Invalidate all configuration caches
     */
    public function invalidateAll(): void
    {
        Cache::forget($this->cacheKey('currencies'));
    }
}
