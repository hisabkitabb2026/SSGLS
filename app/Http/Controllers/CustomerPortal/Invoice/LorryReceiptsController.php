<?php

namespace App\Http\Controllers\CustomerPortal\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\LorryReceiptResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Lorry Receipts Controller for the Customer Portal.
 *
 * Lists and shows invoices with template_name = 'lorry_receipt' where the
 * authenticated customer is the owner, driver, or broker.
 */
class LorryReceiptsController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();

        $query = Invoice::where('template_name', 'lorry_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($customerId) {
                $q->where('owner_customer_id', $customerId)
                    ->orWhere('driver_customer_id', $customerId)
                    ->orWhere('broker_customer_id', $customerId);
            })
            ->with(['customer', 'ownerCustomer', 'driverCustomer', 'brokerCustomer', 'currency'])
            ->latest();

        $invoices = $query->paginate($limit);

        return LorryReceiptResource::collection($invoices)
            ->additional(['meta' => [
                'lorryReceiptTotalCount' => Invoice::where('template_name', 'lorry_receipt')
                    ->where('status', '<>', 'DRAFT')
                    ->where(function ($q) use ($customerId) {
                        $q->where('owner_customer_id', $customerId)
                            ->orWhere('driver_customer_id', $customerId)
                            ->orWhere('broker_customer_id', $customerId);
                    })
                    ->count(),
            ]]);
    }

    public function show(Company $company, $id)
    {
        $customerId = Auth::guard('customer')->id();

        $invoice = Invoice::where('template_name', 'lorry_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($customerId) {
                $q->where('owner_customer_id', $customerId)
                    ->orWhere('driver_customer_id', $customerId)
                    ->orWhere('broker_customer_id', $customerId);
            })
            ->with(['customer', 'ownerCustomer', 'driverCustomer', 'brokerCustomer', 'currency'])
            ->findOrFail($id);

        return new LorryReceiptResource($invoice);
    }
}
