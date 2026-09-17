<?php

namespace App\Http\Controllers\Company\WarehouseItem;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseItemRequest;
use App\Http\Requests\UpdateWarehouseItemRequest;
use App\Http\Resources\WarehouseItemResource;
use App\Http\Resources\WarehouseItemSummaryResource;
use App\Models\Customer;
use App\Models\WarehouseItem;
use App\Services\Document\WarehouseItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseItemController extends Controller
{
    public function __construct(
        private WarehouseItemService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $filters = [
            'status' => $request->query('status'),
            'destination' => $request->query('destination'),
            'warehouse_location' => $request->query('warehouse_location'),
        ];

        $items = $this->service->getCompanyItems($request->header('company'), $filters);

        return WarehouseItemResource::collection($items);
    }

    public function show(Request $request, int $id): WarehouseItemResource
    {
        $item = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $item, 404);

        $this->authorize('view', $item);

        return new WarehouseItemResource($item);
    }

    public function store(StoreWarehouseItemRequest $request): WarehouseItemResource
    {
        $this->authorize('create', WarehouseItem::class);

        $item = $this->service->create([
            'company_id' => $request->header('company'),
            'lr_id' => $request->validated('lr_id'),
            'warehouse_location' => $request->validated('warehouse_location'),
            'section_name' => $request->validated('section_name'),
            'date_received' => $request->validated('date_received'),
            'destination_city' => $request->validated('destination_city'),
            'load_type' => $request->validated('load_type'),
            'promised_dispatch_date' => $request->validated('promised_dispatch_date'),
            'weight_kg' => $request->validated('weight_kg'),
            'no_of_packages' => $request->validated('no_of_packages'),
            'goods_description' => $request->validated('goods_description'),
            'consignor_name' => $request->validated('consignor_name'),
            'consignee_name' => $request->validated('consignee_name'),
            'priority' => $request->validated('priority'),
            'notes' => $request->validated('notes'),
        ]);

        return new WarehouseItemResource($item->load('lr', 'company'));
    }

    public function update(UpdateWarehouseItemRequest $request, int $id): WarehouseItemResource
    {
        $item = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $item, 404);

        $this->authorize('update', $item);

        $updated = $this->service->update($item, $request->validated());

        return new WarehouseItemResource($updated->load('lr', 'company'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $item, 404);

        $this->authorize('delete', $item);

        $this->service->delete($item);

        return response()->json(['message' => 'Warehouse item deleted']);
    }

    public function byDestination(Request $request, string $destination): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $items = $this->service->getItemsByDestination($request->header('company'), $destination);

        return WarehouseItemResource::collection($items);
    }

    public function byLocation(Request $request, string $location): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $items = $this->service->getItemsByLocation($request->header('company'), $location);

        return WarehouseItemResource::collection($items);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $stats = $this->service->getDashboardStats($request->header('company'));

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function destinationSummary(Request $request, string $destination): WarehouseItemSummaryResource
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $summary = $this->service->getDestinationSummary($request->header('company'), $destination);

        return new WarehouseItemSummaryResource($summary);
    }

    public function destinations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $destinations = $this->service->getDestinations($request->header('company'));

        return response()->json([
            'data' => $destinations,
        ]);
    }

    public function warehouses(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $warehouses = $this->service->getWarehouses($request->header('company'));

        return response()->json([
            'data' => $warehouses,
        ]);
    }

    public function lrOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $options = $this->service->getLrOptions($request->header('company'));

        return response()->json([
            'data' => $options->map(fn ($lr) => [
                'id' => $lr->id,
                'invoice_number' => $lr->invoice_number,
                'customer' => [
                    'id' => $lr->customer->id,
                    'name' => $lr->customer->name,
                ],
                'quantity' => $lr->quantity,
                'weight' => $lr->transport_total_weight,
                'price' => $lr->total,
            ]),
        ]);
    }

    public function lookupLr(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $validated = $request->validate(['invoice_number' => 'required|string']);

        $lr = $this->service->findLrByInvoiceNumber(
            $request->header('company'),
            $validated['invoice_number']
        );

        if (! $lr) {
            return response()->json(['error' => 'LR not found'], 404);
        }

        // Destination is either to_name or to_code
        $destination = $lr->to_name ?: $lr->to_code;

        // If no destination, try consignee customer name
        if (! $destination && $lr->consignee_customer_id) {
            $consignee = Customer::find($lr->consignee_customer_id);
            $destination = $consignee?->name;
        }

        return response()->json([
            'data' => [
                'id' => $lr->id,
                'invoice_number' => $lr->invoice_number,
                'customer' => [
                    'id' => $lr->customer->id,
                    'name' => $lr->customer->name,
                ],
                'to_name' => $destination,
                'to_code' => $lr->to_code,
                'description_of_goods' => $lr->description_of_goods,
                'eway_bill_no' => $lr->eway_bill_no,
                'actual_weight' => $lr->actual_weight,
                'no_of_articles' => $lr->no_of_articles,
                'packing' => $lr->packing,
                'quantity' => $lr->quantity,
                'hsn_code' => $lr->hsn_code,
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): WarehouseItemResource
    {
        $item = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $item, 404);

        $this->authorize('update', $item);

        $request->validate([
            'status' => 'required|in:'.implode(',', WarehouseItem::getStatuses()),
        ]);

        $updated = $this->service->updateStatus($item, $request->validated('status'));

        return new WarehouseItemResource($updated->load('lr', 'company'));
    }

    /**
     * Get overdue items: items past their promised dispatch date still in warehouse.
     */
    public function overdue(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $items = $this->service->getOverdueItems($request->header('company'));

        return WarehouseItemResource::collection($items);
    }

    /**
     * Get aging report: items grouped by how long they've been in the warehouse.
     */
    public function agingReport(Request $request): JsonResponse
    {
        $this->authorize('viewAny', WarehouseItem::class);

        $report = $this->service->getAgingReport($request->header('company'));

        return response()->json([
            'data' => $report,
        ]);
    }
}
