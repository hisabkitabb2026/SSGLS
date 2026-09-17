<?php

namespace App\Http\Controllers\Company\Truck;

use App\Http\Controllers\Controller;
use App\Models\Truck;
use App\Services\Document\FleetReportService;
use Illuminate\Http\Request;

class FleetReportController extends Controller
{
    public function __construct(private FleetReportService $service) {}

    /**
     * Lightweight summary stats — no trip rows, fast even with thousands of trips.
     */
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        return response()->json(['data' => $this->service->getSummaryStats($request->header('company'))]);
    }

    /**
     * Paginated trip rows for the financial overview table.
     * Supports ?page=N, ?status, ?lr, ?driver, ?truck_number, ?route filters.
     */
    public function trips(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:planned,dispatched,delivered,cancelled',
            'lr' => 'nullable|string|max:100',
            'driver' => 'nullable|string|max:100',
            'truck_number' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:100',
        ]);

        $result = $this->service->getTripsPaginated(
            companyId: $request->header('company'),
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 10),
            status: $validated['status'] ?? null,
            lr: $validated['lr'] ?? null,
            driver: $validated['driver'] ?? null,
            truckNumber: $validated['truck_number'] ?? null,
            route: $validated['route'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Date-range filtered KPI summary for the Fleet Report tab.
     */
    public function reportSummary(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getReportSummary(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Per-truck performance breakdown.
     */
    public function truckPerformance(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getTruckPerformance(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Expense breakdown by category.
     */
    public function expenseByCategory(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getExpenseByCategory(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Per-route performance analysis.
     */
    public function routePerformance(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getRoutePerformance(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }
}
