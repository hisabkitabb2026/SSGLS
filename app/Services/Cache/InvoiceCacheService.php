<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

/**

Manages caching for Invoice data to improve performance
*/
class InvoiceCacheService +{
private const CACHE_TTL = 3600; // 1 hour

/**
* Cache key prefix
*/
private function cacheKey(string $key): string
{
  return "invoices:{$key}";
}

/**
* Company-scoped cache tags
*/
private function cacheTags(int $companyId): array
{
  return ['invoices', "company:{$companyId}"];
}

/**
* Get invoice list for company (cached)
*/
public function getCompanyInvoices(int $companyId, array $filters = [])
{
  $filterKey = md5(json_encode($filters));
  $key = $this->cacheKey("company:{$companyId}:list:{$filterKey}");

  return Cache::tags($this->cacheTags($companyId))
      ->remember($key, self::CACHE_TTL, function () use ($companyId, $filters) {
          $query = Invoice::where('company_id', $companyId);

          if (isset($filters['template_name'])) {
              $query->where('template_name', $filters['template_name']);
          }

          return $query->get();
      });
}

/**
* Get single invoice (cached)
*/
public function getInvoice(int $invoiceId, int $companyId)
{
  $key = $this->cacheKey("id:{$invoiceId}");

  return Cache::tags($this->cacheTags($companyId))
      ->remember($key, self::CACHE_TTL, function () use ($invoiceId) {
          return Invoice::find($invoiceId);
      });
}

/**
* Invalidate invoice cache on update
*/
public function invalidate(Invoice $invoice): void
{
  Cache::tags($this->cacheTags($invoice->company_id))->flush();
}

/**
* Invalidate company invoices on any change
*/
public function invalidateCompany(int $companyId): void
{
  Cache::tags($this->cacheTags($companyId))->flush();
}

/**
* Get cached statistics
*/
public function getStatistics(int $companyId)
{
  $key = $this->cacheKey("company:{$companyId}:stats");

  return Cache::tags($this->cacheTags($companyId))
      ->remember($key, self::CACHE_TTL * 2, function () use ($companyId) {
          return [
              'total_invoices' => Invoice::where('company_id', $companyId)->count(),
              'total_amount' => Invoice::where('company_id', $companyId)->sum('total'),
              'by_template' => Invoice::where('company_id', $companyId)
                  ->groupBy('template_name')
                  ->selectRaw('template_name, COUNT(*) as count')
                  ->get(),
          ];
      });
}
}