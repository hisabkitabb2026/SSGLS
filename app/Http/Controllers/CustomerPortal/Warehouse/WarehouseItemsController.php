<?php

namespace App\Http\Controllers\CustomerPortal\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\WarehouseItemResource;
use App\Models\Company;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Warehouse Items Controller for the Customer Portal.
 *
 * Lists warehouse items whose LR (Invoice with template_name='lr_receipt')
 * belongs to the authenticated customer (as consignor or consignee).
 */
class WarehouseItemsController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();

        $query = WarehouseItem::whereHas('lr', function ($q) use ($customerId) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($customerId) {
                    $sq->where('customer_id', $customerId)
                        ->orWhere('consignee_customer_id', $customerId);
                });
        })
            ->with(['lr'])
            ->when($request->has('lr_id') && $request->lr_id, function ($q) use ($request) {
                $q->where('lr_id', $request->lr_id);
            })
            ->latest();

        $items = $query->paginate($limit);

        return WarehouseItemResource::collection($items)
            ->additional(['meta' => [
                'warehouseItemTotalCount' => WarehouseItem::whereHas('lr', function ($q) use ($customerId) {
                    $q->where('template_name', 'lr_receipt')
                        ->where('status', '<>', 'DRAFT')
                        ->where(function ($sq) use ($customerId) {
                            $sq->where('customer_id', $customerId)
                                ->orWhere('consignee_customer_id', $customerId);
                        });
                })->count(),
            ]]);
    }

    public function show(Company $company, $id)
    {
        $customerId = Auth::guard('customer')->id();

        $item = WarehouseItem::whereHas('lr', function ($q) use ($customerId) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($customerId) {
                    $sq->where('customer_id', $customerId)
                        ->orWhere('consignee_customer_id', $customerId);
                });
        })
            ->with(['lr', 'consolidation', 'loadTrip'])
            ->findOrFail($id);

        return new WarehouseItemResource($item);
    }
}
