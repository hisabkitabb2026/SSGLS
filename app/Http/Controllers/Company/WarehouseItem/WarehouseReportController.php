<?php

namespace App\Http\Controllers\Company\WarehouseItem;

use App\Http\Controllers\Controller;
use App\Models\WarehouseItem;
use App\Services\Document\WarehouseReportService;
use Illuminate\Http\Request;

/**
 * WarehouseReportController
 *
 * Operational reporting endpoints for the warehouse module.
 * Returns JSON data for the "Reports" tab in Warehouse Operations.
 */
class WarehouseReportController extends Controller
{
    public function __construct(private WarehouseReportService $service) {}

    /**
     * KPI summary — aggregate counts.
     */
    public function summary(Request $request)
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getSummaryStats(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Per-LR breakdown — paginated.
     */
    public function lrSummary(Request $request)
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $result = $this->service->getLrSummary(
            companyId: $request->header('company'),
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 15),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Aging analysis — bucket counts.
     */
    public function aging(Request $request)
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $data = $this->service->getAgingAnalysis($request->header('company'));

        return response()->json(['data' => $data]);
    }

    /**
     * Destination-wise summary.
     */
    public function destinations(Request $request)
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->service->getDestinationSummary(
            companyId: $request->header('company'),
            fromDate: $validated['from_date'] ?? null,
            toDate: $validated['to_date'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Overdue items list.
     */
    public function overdue(Request $request)
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $data = $this->service->getOverdueItems($request->header('company'));

        return response()->json(['data' => $data]);
    }
}
