<?php

namespace App\Http\Controllers\CustomerPortal\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\LrReceiptResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LR Receipts Controller for the Customer Portal.
 *
 * Lists and shows invoices with template_name = 'lr_receipt' that belong
 * to the authenticated customer (as consignor or consignee).
 *
 * The LR Receipt status is derived from its warehouse items' statuses:
 *   - All items 'delivered' → DELIVERED
 *   - All items 'in_transit' → IN TRANSIT
 *   - All items 'stored' → STORED
 *   - Any item 'picked_for_consolidation' → PICKED
 *   - Any item 'cancelled' → CANCELLED
 */
class LrReceiptsController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();

        $query = Invoice::where('template_name', 'lr_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->orWhere('consignee_customer_id', $customerId);
            })
            ->with(['customer', 'consigneeCustomer', 'items', 'currency', 'warehouseItems'])
            ->latest();

        $invoices = $query->paginate($limit);

        // Compute derived status for each LR Receipt from warehouse items
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->derived_status = $this->computeDerivedStatus($invoice);

            return $invoice;
        });

        // Filter by derived status if requested
        if ($request->has('status') && $request->status) {
            $requestedStatus = $request->status;
            $filtered = $invoices->getCollection()->filter(function ($invoice) use ($requestedStatus) {
                return $invoice->derived_status === $requestedStatus;
            })->values();
            $invoices->setCollection($filtered);
        }

        return LrReceiptResource::collection($invoices)

            ->additional(['meta' => [
                'lrReceiptTotalCount' => Invoice::where('template_name', 'lr_receipt')
                    ->where('status', '<>', 'DRAFT')
                    ->where(function ($q) use ($customerId) {
                        $q->where('customer_id', $customerId)
                            ->orWhere('consignee_customer_id', $customerId);
                    })
                    ->count(),
            ]]);
    }

    public function show(Company $company, $id)
    {
        $customerId = Auth::guard('customer')->id();

        $invoice = Invoice::where('template_name', 'lr_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                    ->orWhere('consignee_customer_id', $customerId);
            })
            ->with(['customer', 'consigneeCustomer', 'items', 'items.fields', 'items.fields.customField', 'taxes', 'fields', 'fields.customField', 'currency', 'warehouseItems'])
            ->findOrFail($id);

        $invoice->derived_status = $this->computeDerivedStatus($invoice);

        return new LrReceiptResource($invoice);
    }

    /**
     * Compute the derived status of an LR Receipt from its warehouse items.
     */
    private function computeDerivedStatus(Invoice $invoice): string
    {
        $items = $invoice->warehouseItems;

        if ($items->isEmpty()) {
            return 'STORED';
        }

        $statuses = $items->pluck('status')->toArray();

        $allDelivered = ! in_array(false, array_map(fn ($s) => $s === 'delivered', $statuses));
        $allInTransit = ! in_array(false, array_map(fn ($s) => $s === 'in_transit', $statuses));
        $allStored = ! in_array(false, array_map(fn ($s) => $s === 'stored', $statuses));
        $anyPicked = in_array('picked_for_consolidation', $statuses) || in_array('loaded_on_vehicle', $statuses);
        $anyCancelled = in_array('cancelled', $statuses);

        if ($allDelivered) {
            return 'DELIVERED';
        }
        if ($allInTransit) {
            return 'IN TRANSIT';
        }
        if ($anyCancelled) {
            return 'CANCELLED';
        }
        if ($anyPicked) {
            return 'PICKED';
        }
        if ($allStored) {
            return 'STORED';
        }

        return 'STORED';
    }
}
