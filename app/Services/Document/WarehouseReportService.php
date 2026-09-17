<?php

namespace App\Services\Document;

use App\Models\WarehouseItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * WarehouseReportService
 *
 * Operational reporting for the warehouse module.
 * Answers: "How is material flowing through my warehouse?"
 *
 * All methods are company-scoped and support optional date-range filtering
 * on the `date_received` column.
 *
 * Future scaling:
 * // TODO: Add warehouse-location-level breakdown for multi-warehouse operations
 * // TODO: Add SLA compliance metrics (promised vs actual dispatch date)
 * // TODO: Add consignor/consignee-wise performance analysis
 */
class WarehouseReportService
{
    /**
     * KPI summary — aggregate counts for the report header.
     */
    public function getSummaryStats(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = WarehouseItem::forCompany($companyId);

        if ($fromDate && $toDate) {
            $query->whereBetween('date_received', [$fromDate, $toDate.' 23:59:59']);
        }

        $items = $query->get();

        $activeStatuses = [
            WarehouseItem::STATUS_STORED,
            WarehouseItem::STATUS_PICKED,
            WarehouseItem::STATUS_LOADED,
            WarehouseItem::STATUS_IN_TRANSIT,
        ];

        $storedItems = $items->whereIn('status', $activeStatuses);
        $dispatchedItems = $items->where('status', WarehouseItem::STATUS_DELIVERED);
        $overdueItems = $items->filter(fn ($i) => $i->is_overdue);

        // Distinct LRs with active items (not delivered/cancelled)
        $activeLrIds = $storedItems->pluck('lr_id')->unique();

        // Average days in warehouse for active items
        $avgDays = $storedItems->avg(fn ($item) => $item->days_in_warehouse ?? 0);

        return [
            'lrs_received' => $items->pluck('lr_id')->unique()->count(),
            'items_stored' => $storedItems->count(),
            'items_dispatched' => $dispatchedItems->count(),
            'overdue_items' => $overdueItems->count(),
            'avg_days_in_warehouse' => round($avgDays ?? 0, 1),
            'total_weight_kg' => (float) $storedItems->sum('weight_kg'),
            'active_lrs' => $activeLrIds->count(),
        ];
    }

    /**
     * Per-LR breakdown — one row per LR with aggregate stats.
     * Paginated server-side.
     */
    public function getLrSummary(int $companyId, int $page = 1, int $perPage = 15, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = DB::table('warehouse_items as wi')
            ->join('invoices as i', 'wi.lr_id', '=', 'i.id')
            ->leftJoin('customers as c', 'i.customer_id', '=', 'c.id')
            ->where('wi.company_id', $companyId)
            ->select(
                'wi.lr_id',
                'i.invoice_number as lr_number',
                'c.name as customer_name',
                'wi.destination_city',
                DB::raw('SUM(wi.weight_kg) as total_weight_kg'),
                DB::raw('SUM(wi.no_of_packages) as total_packages'),
                DB::raw('MIN(wi.date_received) as received_date'),
                DB::raw('MAX(wi.promised_dispatch_date) as promised_dispatch_date'),
                DB::raw('COUNT(wi.id) as item_count'),
                DB::raw('MAX(CASE WHEN wi.status = "delivered" THEN 1 ELSE 0 END) as is_delivered'),
                DB::raw('MAX(CASE WHEN wi.promised_dispatch_date IS NOT NULL AND wi.promised_dispatch_date < CURDATE() AND wi.status IN ("stored", "picked_for_consolidation") THEN 1 ELSE 0 END) as is_overdue')
            )
            ->groupBy('wi.lr_id', 'i.invoice_number', 'c.name', 'wi.destination_city');

        if ($fromDate && $toDate) {
            $query->whereBetween('wi.date_received', [$fromDate, $toDate.' 23:59:59']);
        }

        $paginated = $query->orderByDesc('received_date')->paginate($perPage, ['*'], 'page', $page);

        $data = collect($paginated->items())->map(function ($row) {
            $daysInWh = $row->received_date
                ? Carbon::parse($row->received_date)->diffInDays(now())
                : 0;

            return [
                'lr_id' => $row->lr_id,
                'lr_number' => $row->lr_number,
                'customer_name' => $row->customer_name,
                'destination_city' => $row->destination_city,
                'total_weight_kg' => (float) $row->total_weight_kg,
                'total_packages' => (int) $row->total_packages,
                'item_count' => (int) $row->item_count,
                'received_date' => $row->received_date,
                'promised_dispatch_date' => $row->promised_dispatch_date,
                'days_in_warehouse' => $daysInWh,
                'is_delivered' => (bool) $row->is_delivered,
                'is_overdue' => (bool) $row->is_overdue,
            ];
        })->values();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * Aging analysis — bucket items by days in warehouse.
     */
    public function getAgingAnalysis(int $companyId): array
    {
        $items = WarehouseItem::forCompany($companyId)
            ->whereIn('status', [
                WarehouseItem::STATUS_STORED,
                WarehouseItem::STATUS_PICKED,
            ])
            ->get();

        $buckets = [
            '0-3' => ['count' => 0, 'total_weight_kg' => 0],
            '4-7' => ['count' => 0, 'total_weight_kg' => 0],
            '8-15' => ['count' => 0, 'total_weight_kg' => 0],
            '15+' => ['count' => 0, 'total_weight_kg' => 0],
        ];

        $totalCount = $items->count();
        $totalWeight = (float) $items->sum('weight_kg');

        foreach ($items as $item) {
            $bucket = $item->aging_bucket;
            $buckets[$bucket]['count']++;
            $buckets[$bucket]['total_weight_kg'] += (float) $item->weight_kg;
        }

        return collect($buckets)->map(function ($data, $bucket) use ($totalCount) {
            return [
                'bucket' => $bucket,
                'item_count' => $data['count'],
                'total_weight_kg' => round($data['total_weight_kg'], 2),
                'percentage' => $totalCount > 0 ? round(($data['count'] / $totalCount) * 100, 1) : 0,
            ];
        })->values()->all();
    }

    /**
     * Destination-wise summary — per-destination aggregate.
     */
    public function getDestinationSummary(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = WarehouseItem::forCompany($companyId)
            ->whereIn('status', [
                WarehouseItem::STATUS_STORED,
                WarehouseItem::STATUS_PICKED,
                WarehouseItem::STATUS_LOADED,
                WarehouseItem::STATUS_IN_TRANSIT,
            ]);

        if ($fromDate && $toDate) {
            $query->whereBetween('date_received', [$fromDate, $toDate.' 23:59:59']);
        }

        $items = $query->get();

        $grouped = $items->groupBy('destination_city');

        return $grouped->map(function ($group, $destination) {
            $overdueCount = $group->filter(fn ($i) => $i->is_overdue)->count();
            $avgDays = $group->avg(fn ($i) => $i->days_in_warehouse ?? 0);

            return [
                'destination' => $destination ?: 'Unknown',
                'total_lrs' => $group->pluck('lr_id')->unique()->count(),
                'total_weight_kg' => round((float) $group->sum('weight_kg'), 2),
                'item_count' => $group->count(),
                'overdue_count' => $overdueCount,
                'avg_days' => round($avgDays ?? 0, 1),
            ];
        })->sortByDesc('total_weight_kg')->values()->all();
    }

    /**
     * Overdue items — items past their promised dispatch date, still in warehouse.
     */
    public function getOverdueItems(int $companyId): array
    {
        $items = WarehouseItem::forCompany($companyId)
            ->with(['lr.customer'])
            ->overdue()
            ->orderBy('promised_dispatch_date')
            ->get();

        return $items->map(function ($item) {
            $daysOverdue = $item->promised_dispatch_date
                ? now()->startOfDay()->diffInDays(Carbon::parse($item->promised_dispatch_date)->startOfDay(), false)
                : 0;

            return [
                'id' => $item->id,
                'lr_number' => $item->lr?->invoice_number ?? '—',
                'customer_name' => $item->lr?->customer?->name ?? $item->consignor_name ?? '—',
                'destination_city' => $item->destination_city,
                'weight_kg' => (float) $item->weight_kg,
                'packages' => $item->no_of_packages,
                'promised_dispatch_date' => $item->promised_dispatch_date?->format('Y-m-d'),
                'days_overdue' => abs($daysOverdue),
                'warehouse_location' => $item->warehouse_location,
            ];
        })->values()->all();
    }
}
