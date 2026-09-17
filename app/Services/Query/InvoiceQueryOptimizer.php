<?php

declare(strict_types=1);

namespace App\Services\Query;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

/**
 * Optimizes Invoice queries by managing relationships and eager loading.
 * Prevents N+1 query problems by ensuring proper relationship loading.
 */
class InvoiceQueryOptimizer
{
    /**
     * Relationships to eager load for full invoice context
     */
    private static array $fullRelations = [
        'customer',
        'consigneeCustomer',
        'items',
        'items.taxes',
        'items.fields',
        'items.fields.customField',
        'payments',
        'taxes',
        'fields',
        'fields.customField',
        'tags',
        'recurringInvoice',
        'creator',
        'company',
    ];

    /**
     * Minimal relationships for list views
     */
    private static array $listRelations = [
        'customer',
        'payments',
        'creator',
    ];

    /**
     * Transport-specific relationships
     */
    private static array $transportRelations = [
        'customer',
        'consigneeCustomer',
        'items',
        'payments',
        'creator',
    ];

    /**
     * Apply full eager loading for complete invoice data
     */
    public static function withAllRelations(Builder $query): Builder
    {
        return $query->with(self::$fullRelations);
    }

    /**
     * Apply minimal eager loading for list views
     */
    public static function forList(Builder $query): Builder
    {
        return $query->with(self::$listRelations);
    }

    /**
     * Apply transport-specific eager loading
     */
    public static function forTransport(Builder $query): Builder
    {
        return $query->with(self::$transportRelations);
    }

    /**
     * Count distinct relationships to avoid duplicates
     */
    public static function countDistinct(Builder $query, string $column = 'id'): int
    {
        return $query->distinct()->count($column);
    }

    /**
     * Paginate with optimized queries
     */
    public static function paginateOptimized(Builder $query, int $perPage = 15)
    {
        return self::forList($query)->paginate($perPage);
    }

    /**
     * Get single invoice with all data
     */
    public static function findWithAll(int|string $id): ?Invoice
    {
        return self::withAllRelations(Invoice::query())->find($id);
    }

    /**
     * Optimized query for recent invoices (with limit)
     */
    public static function recentInvoices(Builder $query, int $limit = 10)
    {
        return self::forList($query)->latest()->limit($limit)->get();
    }

    /**
     * Optimized query for invoice statistics (no unnecessary relations)
     */
    public static function forStats(Builder $query): Builder
    {
        return $query->select([
            'id',
            'company_id',
            'customer_id',
            'total',
            'base_total',
            'tax',
            'due_amount',
            'base_due_amount',
            'template_name',
            'invoice_date',
        ]);
    }

    /**
     * Apply performance optimizations without loading all relations
     */
    public static function minimal(Builder $query): Builder
    {
        return $query->select([
            'id',
            'company_id',
            'customer_id',
            'invoice_number',
            'invoice_date',
            'total',
            'base_total',
        ])->with(['customer:id,name,company_id']);
    }
}
