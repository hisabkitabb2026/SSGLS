<?php

namespace App\Services\Document;

use App\Models\Expense;
use App\Models\LoadTrip;
use App\Models\Truck;
use App\Models\WarehouseItem;
use Carbon\Carbon;

class FleetReportService
{
    /**
     * Lightweight summary stats — aggregate queries only, no trip rows.
     * Loads fast even with thousands of trips.
     */
    public function getSummaryStats(int $companyId): array
    {
        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->where(fn ($query) => $query->whereNotNull('truck_id')->orWhereNotNull('load_trip_id'))
            ->get();
        $trucks = Truck::forCompany($companyId)->get();

        // Trip pipeline counts by status (aggregate query, no eager loading)
        $pipeline = [
            'planned' => LoadTrip::forCompany($companyId)->where('status', LoadTrip::STATUS_PLANNED)->count(),
            'dispatched' => LoadTrip::forCompany($companyId)->where('status', LoadTrip::STATUS_DISPATCHED)->count(),
            'delivered' => LoadTrip::forCompany($companyId)->where('status', LoadTrip::STATUS_DELIVERED)->count(),
            'cancelled' => LoadTrip::forCompany($companyId)->where('status', LoadTrip::STATUS_CANCELLED)->count(),
        ];

        // Active trips (dispatched only — naturally a small set)
        $activeTrips = LoadTrip::forCompany($companyId)
            ->where('status', LoadTrip::STATUS_DISPATCHED)
            ->with(['truck', 'driverProfile', 'warehouseItems.lr'])
            ->get()
            ->map(function (LoadTrip $trip) {
                $daysElapsed = $trip->dispatch_date
                    ? Carbon::parse($trip->dispatch_date)->diffInDays(now())
                    : 0;

                return [
                    'id' => $trip->id,
                    'trip_number' => $trip->trip_number,
                    'route' => ($trip->origin_city ? $trip->origin_city.' → ' : '').$trip->destination_city,
                    'truck_number' => $trip->truck?->truck_number ?? $trip->truck_number,
                    'driver_name' => $trip->driver_name,
                    'driver_phone' => $trip->driver_phone,
                    'dispatch_date' => $trip->dispatch_date?->format('Y-m-d'),
                    'days_elapsed' => $daysElapsed,
                    'lr_count' => $trip->warehouseItems->filter(fn ($i) => $i->lr)->map(fn ($i) => $i->lr_id)->unique()->count(),
                    'total_weight_kg' => (float) $trip->warehouseItems->sum('weight_kg'),
                ];
            })->values();

        // Total revenue — sum across all trips (uses the same revenue calc)
        $allTrips = LoadTrip::forCompany($companyId)->with('warehouseItems.lr')->get();
        $totalRevenue = (int) $allTrips->sum(fn (LoadTrip $trip) => $this->getTripRevenue($trip));
        $totalExpense = (int) $expenses->sum('base_amount');

        return [
            'total_trip_revenue' => $totalRevenue,
            'total_fleet_expense' => $totalExpense,
            'total_trip_margin' => $totalRevenue - $totalExpense,
            'by_truck' => $trucks->map(fn (Truck $truck) => [
                'truck_id' => $truck->id,
                'truck_number' => $truck->truck_number,
                'expense_total' => (int) $expenses->where('truck_id', $truck->id)->sum('base_amount'),
            ])->values(),
            'by_category' => $expenses->groupBy('expense_category_id')->map(fn ($group) => [
                'expense_category_id' => $group->first()->expense_category_id,
                'expense_total' => (int) $group->sum('base_amount'),
            ])->values(),
            'trip_pipeline' => $pipeline,
            'active_trips' => $activeTrips,
        ];
    }

    /**
     * Paginated trip rows for the financial overview table.
     * Loads only one page at a time (default 10 per page).
     * Supports filtering by status, LR number, driver name, truck number, and route.
     */
    public function getTripsPaginated(
        int $companyId,
        int $page = 1,
        int $perPage = 10,
        ?string $status = null,
        ?string $lr = null,
        ?string $driver = null,
        ?string $truckNumber = null,
        ?string $route = null,
    ): array {
        $query = LoadTrip::forCompany($companyId)
            ->with(['truck', 'driverProfile', 'warehouseItems.lr']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($lr) {
            $query->whereHas('warehouseItems.lr', function ($q) use ($lr) {
                $q->where('invoice_number', 'like', "%{$lr}%")
                    ->orWhere('lr_number', 'like', "%{$lr}%");
            });
        }

        if ($driver) {
            $query->where('driver_name', 'like', "%{$driver}%");
        }

        if ($truckNumber) {
            $query->where(function ($q) use ($truckNumber) {
                $q->where('truck_number', 'like', "%{$truckNumber}%")
                    ->orWhereHas('truck', function ($q2) use ($truckNumber) {
                        $q2->where('truck_number', 'like', "%{$truckNumber}%");
                    });
            });
        }

        if ($route) {
            $query->where(function ($q) use ($route) {
                $q->where('origin_city', 'like', "%{$route}%")
                    ->orWhere('destination_city', 'like', "%{$route}%");
            });
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        // Pre-load expenses for just this page's trips (not all expenses)
        $tripIds = $paginated->getCollection()->pluck('id');
        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->whereIn('load_trip_id', $tripIds)
            ->get();

        $tripRows = $paginated->getCollection()->map(function (LoadTrip $trip) use ($expenses) {
            $revenue = $this->getTripRevenue($trip);
            $expense = (int) $expenses->where('load_trip_id', $trip->id)->sum('base_amount');
            $lrNumbers = $trip->warehouseItems
                ->filter(fn ($item) => $item->lr)
                ->map(fn ($item) => $item->lr->invoice_number ?? $item->lr->lr_number ?? '—')
                ->unique()
                ->values()
                ->all();
            $totalWeight = (float) $trip->warehouseItems->sum('weight_kg');
            $totalItems = $trip->warehouseItems->count();

            return [
                'load_trip_id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'route' => ($trip->origin_city ? $trip->origin_city.' → ' : '').$trip->destination_city,
                'truck_number' => $trip->truck?->truck_number ?? $trip->truck_number,
                'driver_name' => $trip->driver_name,
                'status' => $trip->status,
                'dispatch_date' => $trip->dispatch_date?->format('Y-m-d'),
                'actual_delivery_date' => $trip->actual_delivery_date?->format('Y-m-d'),
                'lr_numbers' => $lrNumbers,
                'total_weight_kg' => $totalWeight,
                'total_items' => $totalItems,
                'expense_total' => $expense,
                'revenue_total' => $revenue,
                'margin_total' => $revenue - $expense,
            ];
        })->values();

        return [
            'data' => $tripRows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    private function getTripRevenue(LoadTrip $trip): int
    {
        $tripItems = $trip->warehouseItems->filter(fn ($item) => $item->lr_id !== null)->groupBy('lr_id');

        return (int) $tripItems->sum(function ($items, int $lrId) {
            $lr = $items->first()->lr;
            $totalWeight = (float) WarehouseItem::query()->where('lr_id', $lrId)->where('status', '!=', WarehouseItem::STATUS_CANCELLED)->sum('weight_kg');
            $tripWeight = (float) $items->sum('weight_kg');

            return $lr && $totalWeight > 0 ? round($lr->total * ($tripWeight / $totalWeight)) : 0;
        });
    }

    /**
     * Date-range filtered KPI summary for the Fleet Report tab.
     */
    public function getReportSummary(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = LoadTrip::forCompany($companyId);
        $expenseQuery = Expense::query()->where('company_id', $companyId);

        if ($fromDate && $toDate) {
            $query->whereBetween('dispatch_date', [$fromDate, $toDate.' 23:59:59']);
            $expenseQuery->where(function ($q) use ($fromDate, $toDate) {
                $q->whereHas('loadTrip', function ($qt) use ($fromDate, $toDate) {
                    $qt->whereBetween('dispatch_date', [$fromDate, $toDate.' 23:59:59']);
                })->orWhereNull('load_trip_id');
            });
        }

        $trips = $query->with(['truck', 'warehouseItems.lr'])->get();
        $expenses = $expenseQuery->get();

        $totalRevenue = (int) $trips->sum(fn (LoadTrip $trip) => $this->getTripRevenue($trip));
        $totalExpense = (int) $expenses->sum('base_amount');
        $totalTrips = $trips->count();

        // Fleet utilization: trips with trucks / total trucks
        $totalTrucks = Truck::forCompany($companyId)->count();
        $trucksOnTrips = $trips->filter(fn ($t) => $t->truck_id)->pluck('truck_id')->unique()->count();

        return [
            'total_trips' => $totalTrips,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_margin' => $totalRevenue - $totalExpense,
            'avg_margin_per_trip' => $totalTrips > 0 ? (int) round(($totalRevenue - $totalExpense) / $totalTrips) : 0,
            'fleet_utilization' => $totalTrucks > 0 ? round(($trucksOnTrips / $totalTrucks) * 100, 1) : 0,
        ];
    }

    /**
     * Per-truck performance breakdown.
     */
    public function getTruckPerformance(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = LoadTrip::forCompany($companyId)->with(['truck', 'warehouseItems.lr']);

        if ($fromDate && $toDate) {
            $query->whereBetween('dispatch_date', [$fromDate, $toDate.' 23:59:59']);
        }

        $trips = $query->get();
        $truckIds = $trips->filter(fn ($t) => $t->truck_id)->pluck('truck_id')->unique();
        $trucks = Truck::forCompany($companyId)->whereIn('id', $truckIds)->get()->keyBy('id');

        $expenses = Expense::query()
            ->where('company_id', $companyId)
            ->whereIn('truck_id', $truckIds)
            ->get();

        $grouped = $trips->groupBy('truck_id');

        return $grouped->map(function ($truckTrips, $truckId) use ($trucks, $expenses) {
            $truck = $trucks->get($truckId);
            $revenue = (int) $truckTrips->sum(fn (LoadTrip $trip) => $this->getTripRevenue($trip));
            $expense = (int) $expenses->where('truck_id', $truckId)->sum('base_amount');

            return [
                'truck_id' => $truckId,
                'truck_number' => $truck?->truck_number ?? '—',
                'trip_count' => $truckTrips->count(),
                'total_revenue' => $revenue,
                'total_expense' => $expense,
                'margin' => $revenue - $expense,
                'avg_margin_per_trip' => $truckTrips->count() > 0
                    ? (int) round(($revenue - $expense) / $truckTrips->count())
                    : 0,
            ];
        })->sortByDesc('margin')->values()->all();
    }

    /**
     * Expense breakdown by category.
     */
    public function getExpenseByCategory(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = Expense::query()
            ->where('company_id', $companyId)
            ->whereNotNull('truck_id');

        if ($fromDate && $toDate) {
            $query->whereBetween('created_at', [$fromDate, $toDate.' 23:59:59']);
        }

        $expenses = $query->get();
        $total = (int) $expenses->sum('base_amount');

        $grouped = $expenses->groupBy('expense_category_id');

        return $grouped->map(function ($group, $categoryId) use ($total) {
            $groupTotal = (int) $group->sum('base_amount');

            return [
                'expense_category_id' => $categoryId,
                'category_name' => $group->first()->expenseCategory?->name ?? 'Uncategorized',
                'total_amount' => $groupTotal,
                'percentage' => $total > 0 ? round(($groupTotal / $total) * 100, 1) : 0,
                'trip_count' => $group->whereNotNull('load_trip_id')->count(),
            ];
        })->sortByDesc('total_amount')->values()->all();
    }

    /**
     * Per-route performance analysis.
     */
    public function getRoutePerformance(int $companyId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = LoadTrip::forCompany($companyId)->with(['truck', 'warehouseItems.lr']);

        if ($fromDate && $toDate) {
            $query->whereBetween('dispatch_date', [$fromDate, $toDate.' 23:59:59']);
        }

        $trips = $query->get();

        $grouped = $trips->groupBy(function ($trip) {
            $origin = $trip->origin_city ?: '—';
            $dest = $trip->destination_city ?: '—';

            return "{$origin} → {$dest}";
        });

        return $grouped->map(function ($routeTrips, $route) {
            $revenue = (int) $routeTrips->sum(fn (LoadTrip $trip) => $this->getTripRevenue($trip));
            $tripCount = $routeTrips->count();
            $mostUsedTruck = $routeTrips->filter(fn ($t) => $t->truck)->groupBy('truck_id')->sortDesc()->keys()->first();
            $truck = $mostUsedTruck ? Truck::find($mostUsedTruck) : null;

            return [
                'route' => $route,
                'trip_count' => $tripCount,
                'total_revenue' => $revenue,
                'avg_revenue_per_trip' => $tripCount > 0 ? (int) round($revenue / $tripCount) : 0,
                'total_weight_kg' => (float) $routeTrips->sum(fn ($t) => $t->warehouseItems->sum('weight_kg')),
                'most_used_truck' => $truck?->truck_number ?? '—',
            ];
        })->sortByDesc('total_revenue')->values()->all();
    }
}
