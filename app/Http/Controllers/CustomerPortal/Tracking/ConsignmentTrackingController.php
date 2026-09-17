<?php

namespace App\Http\Controllers\CustomerPortal\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ConsignmentTrackingResource;
use App\Models\ConsolidationGroup;
use App\Models\Invoice;
use App\Models\LoadTrip;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Consignment Tracking Controller for the Customer Portal.
 *
 * Aggregates data across LR Receipt → Warehouse Item → Consolidation Group → Load Trip
 * to return a unified timeline of a consignment's journey.
 *
 * The customer must own the docket (as consignor or consignee) to track it.
 */
class ConsignmentTrackingController extends Controller
{
    public function __invoke(Request $request, string $docketNumber)
    {
        $customerId = Auth::guard('customer')->id();

        \Log::info('Tracking request', [
            'docketNumber' => $docketNumber,
            'customerId' => $customerId,
        ]);

        // 1. Find the LR Receipt by docket number (invoice_number)
        $lrReceipt = Invoice::where('template_name', 'lr_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where('invoice_number', $docketNumber)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->orWhere('consignee_customer_id', $customerId);
            })
            ->with(['customer', 'consigneeCustomer', 'items', 'currency'])
            ->first();

        if (! $lrReceipt) {
            \Log::info('LR Receipt not found', [
                'docketNumber' => $docketNumber,
                'customerId' => $customerId,
            ]);

            return response()->json([
                'error' => 'consignment_not_found',
                'message' => 'No consignment found with this docket number, or you do not have access to it.',
            ], 404);
        }

        // 2. Find the warehouse item linked to this LR
        $warehouseItem = WarehouseItem::where('lr_id', $lrReceipt->id)
            ->with(['consolidation', 'loadTrip'])
            ->first();

        // 3. Find the consolidation group (if any)
        $consolidationGroup = null;
        if ($warehouseItem && $warehouseItem->consolidation_id) {
            $consolidationGroup = ConsolidationGroup::find($warehouseItem->consolidation_id);
        }

        // 4. Find the load trip (if any)
        $loadTrip = null;
        if ($warehouseItem && $warehouseItem->delivery_id) {
            $loadTrip = LoadTrip::with(['truck', 'driverProfile'])
                ->find($warehouseItem->delivery_id);
        }

        // 5. Build the timeline
        $timeline = $this->buildTimeline($lrReceipt, $warehouseItem, $consolidationGroup, $loadTrip);

        // 6. Determine overall status
        $status = $this->determineStatus($lrReceipt, $warehouseItem, $loadTrip);

        $data = [
            'docket_number' => $lrReceipt->invoice_number,
            'from_name' => $lrReceipt->from_name,
            'to_name' => $lrReceipt->to_name,
            'status' => $status,
            'lr_receipt' => [
                'id' => $lrReceipt->id,
                'invoice_number' => $lrReceipt->invoice_number,
                'invoice_date' => $lrReceipt->invoice_date,
                'formatted_invoice_date' => $lrReceipt->formattedInvoiceDate,
                'total' => $lrReceipt->total,
                'status' => $lrReceipt->status,
            ],
            'warehouse_item' => $warehouseItem ? [
                'id' => $warehouseItem->id,
                'status' => $warehouseItem->status,
                'destination_city' => $warehouseItem->destination_city,
                'warehouse_location' => $warehouseItem->warehouse_location,
                'weight_kg' => $warehouseItem->weight_kg,
                'load_type' => $warehouseItem->load_type,
                'date_received' => $warehouseItem->date_received,
                'promised_dispatch_date' => $warehouseItem->promised_dispatch_date,
                'days_in_warehouse' => $warehouseItem->days_in_warehouse,
                'is_overdue' => $warehouseItem->is_overdue,
            ] : null,
            'consolidation_group' => $consolidationGroup ? [
                'id' => $consolidationGroup->id,
                'status' => $consolidationGroup->status,
                'destination_city' => $consolidationGroup->destination_city,
                'total_weight_kg' => $consolidationGroup->total_weight_kg,
                'truck_capacity_kg' => $consolidationGroup->truck_capacity_kg,
                'fill_percentage' => $consolidationGroup->fill_percentage,
            ] : null,
            'load_trip' => $loadTrip ? [
                'id' => $loadTrip->id,
                'status' => $loadTrip->status,
                'dispatch_date' => $loadTrip->dispatch_date,
                'expected_delivery_date' => $loadTrip->expected_delivery_date,
                'actual_delivery_date' => $loadTrip->actual_delivery_date,
                'truck_number' => $loadTrip->truck?->registration_number,
                'driver_name' => $loadTrip->driverProfile?->name,
            ] : null,
            'timeline' => $timeline,
        ];

        return new ConsignmentTrackingResource($data);
    }

    /**
     * Build a timeline of events for the consignment's journey.
     */
    private function buildTimeline(
        Invoice $lrReceipt,
        ?WarehouseItem $warehouseItem,
        ?ConsolidationGroup $consolidationGroup,
        ?LoadTrip $loadTrip,
    ): array {
        $timeline = [];

        // Event 1: LR Receipt Created
        $timeline[] = [
            'event' => 'LR Receipt Created',
            'date' => $lrReceipt->formatted_invoice_date,
            'detail' => "Docket {$lrReceipt->invoice_number} generated · Route: {$lrReceipt->from_name} → {$lrReceipt->to_name}",
            'status' => 'done',
        ];

        // Event 2: Goods Received at Warehouse
        if ($warehouseItem && $warehouseItem->date_received) {
            $timeline[] = [
                'event' => 'Goods Received at Warehouse',
                'date' => $warehouseItem->date_received->format('d M Y, h:i A'),
                'detail' => "Location: {$warehouseItem->warehouse_location} · Weight: {$warehouseItem->weight_kg} kg · Load Type: {$warehouseItem->load_type}",
                'status' => 'done',
            ];
        }

        // Event 3: Picked for Consolidation
        if ($warehouseItem && in_array($warehouseItem->status, ['picked_for_consolidation', 'loaded_on_vehicle', 'in_transit', 'delivered'])) {
            $timeline[] = [
                'event' => 'Picked for Consolidation',
                'date' => $warehouseItem->updated_at?->format('d M Y, h:i A'),
                'detail' => $consolidationGroup
                    ? "Consolidation Group: CG-{$consolidationGroup->id} · Destination: {$consolidationGroup->destination_city}"
                    : 'Assigned to consolidation group',
                'status' => 'done',
            ];
        }

        // Event 4: Loaded on Truck
        if ($warehouseItem && in_array($warehouseItem->status, ['loaded_on_vehicle', 'in_transit', 'delivered'])) {
            $timeline[] = [
                'event' => 'Loaded on Truck',
                'date' => $loadTrip?->dispatch_date?->format('d M Y, h:i A'),
                'detail' => $loadTrip
                    ? "Truck: {$loadTrip->truck?->registration_number} · Driver: {$loadTrip->driverProfile?->name}"
                    : 'Loaded on vehicle',
                'status' => 'done',
            ];
        }

        // Event 5: Dispatched / In Transit
        if ($loadTrip && in_array($loadTrip->status, ['dispatched', 'delivered'])) {
            $timeline[] = [
                'event' => 'In Transit',
                'date' => $loadTrip->dispatch_date?->format('d M Y, h:i A'),
                'detail' => "Dispatched · Expected delivery: {$loadTrip->expected_delivery_date?->format('d M Y')}",
                'status' => $loadTrip->status === 'delivered' ? 'done' : 'current',
            ];
        }

        // Event 6: Delivered
        if ($loadTrip && $loadTrip->status === 'delivered') {
            $timeline[] = [
                'event' => 'Delivered',
                'date' => $loadTrip->actual_delivery_date?->format('d M Y'),
                'detail' => 'Consignment delivered successfully.',
                'status' => 'done',
            ];
        } else {
            $timeline[] = [
                'event' => 'Delivered',
                'date' => null,
                'detail' => 'Pending delivery.',
                'status' => 'pending',
            ];
        }

        return $timeline;
    }

    /**
     * Determine the overall consignment status.
     */
    private function determineStatus(Invoice $lrReceipt, ?WarehouseItem $warehouseItem, ?LoadTrip $loadTrip): string
    {
        if ($loadTrip && $loadTrip->status === 'delivered') {
            return 'delivered';
        }

        if ($loadTrip && $loadTrip->status === 'dispatched') {
            return 'in_transit';
        }

        if ($warehouseItem && in_array($warehouseItem->status, ['loaded_on_vehicle'])) {
            return 'loaded';
        }

        if ($warehouseItem && in_array($warehouseItem->status, ['picked_for_consolidation'])) {
            return 'picked';
        }

        if ($warehouseItem && $warehouseItem->status === 'stored') {
            return 'at_warehouse';
        }

        return 'created';
    }
}
