<?php

namespace App\Http\Controllers\CustomerPortal\Transport;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\LoadTripResource;
use App\Models\Company;
use App\Models\LoadTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Load Trips Controller for the Customer Portal.
 *
 * Lists load trips that carry the authenticated customer's warehouse items.
 * Customer can track truck dispatch status from planned to delivered.
 *
 * Supports filtering by status, search by trip/truck/destination, and sorting.
 */
class LoadTripsController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? (int) $request->limit : 10;
        $customerId = Auth::guard('customer')->id();

        $query = LoadTrip::whereHas('warehouseItems.lr', function ($q) use ($customerId) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($customerId) {
                    $sq->where('customer_id', $customerId)
                        ->orWhere('consignee_customer_id', $customerId);
                });
        })
            ->with(['truck', 'driverProfile', 'brokerProfile', 'warehouseItems.lr'])
            ->latest();

        // ── Filter by status ──
        if ($request->filled('status') && in_array($request->status, LoadTrip::getStatuses())) {
            $query->where('status', $request->status);
        }

        // ── Search ──
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('trip_number', 'like', "%{$search}%")
                    ->orWhere('truck_number', 'like', "%{$search}%")
                    ->orWhere('destination_city', 'like', "%{$search}%")
                    ->orWhere('origin_city', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%");
            });
        }

        // ── Sort ──
        $orderByField = $request->input('orderByField', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');

        $allowedSortFields = ['dispatch_date', 'expected_delivery_date', 'trip_number', 'created_at'];
        if (in_array($orderByField, $allowedSortFields)) {
            $query->orderBy($orderByField, $orderBy === 'asc' ? 'asc' : 'desc');
        }

        $trips = $query->paginate($limit);

        // Total count (unfiltered, for the customer)
        $totalCount = LoadTrip::whereHas('warehouseItems.lr', function ($q) use ($customerId) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($customerId) {
                    $sq->where('customer_id', $customerId)
                        ->orWhere('consignee_customer_id', $customerId);
                });
        })->count();

        // Status counts for filter tabs
        $statusCounts = [];
        foreach (LoadTrip::getStatuses() as $status) {
            $statusCounts[$status] = LoadTrip::whereHas('warehouseItems.lr', function ($q) use ($customerId) {
                $q->where('template_name', 'lr_receipt')
                    ->where('status', '<>', 'DRAFT')
                    ->where(function ($sq) use ($customerId) {
                        $sq->where('customer_id', $customerId)
                            ->orWhere('consignee_customer_id', $customerId);
                    });
            })->where('status', $status)->count();
        }

        return LoadTripResource::collection($trips)
            ->additional(['meta' => [
                'loadTripTotalCount' => $totalCount,
                'status_counts' => $statusCounts,
            ]]);
    }

    public function show(Company $company, $id)
    {
        $customerId = Auth::guard('customer')->id();

        $trip = LoadTrip::whereHas('warehouseItems.lr', function ($q) use ($customerId) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($customerId) {
                    $sq->where('customer_id', $customerId)
                        ->orWhere('consignee_customer_id', $customerId);
                });
        })
            ->with(['truck', 'driverProfile', 'brokerProfile', 'warehouseItems.lr', 'consolidationGroup'])
            ->findOrFail($id);

        return new LoadTripResource($trip);
    }
}
