<?php

namespace App\Http\Controllers\CustomerPortal\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\OfficeInvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Office Invoices (Invoice Receipts) Controller for the Customer Portal.
 *
 * Lists and shows invoices with template_name = 'office_invoice' that belong
 * to the authenticated customer.
 */
class OfficeInvoicesController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;
        $customerId = Auth::guard('customer')->id();

        $query = Invoice::where('template_name', 'office_invoice')
            ->where('status', '<>', 'DRAFT')
            ->whereCustomer($customerId)
            ->with(['customer', 'items', 'currency'])
            ->latest();

        // Apply status filter (DUE = unpaid, COMPLETED = paid)
        if ($request->has('status') && $request->status) {
            if ($request->status === 'DUE') {
                $query->where('paid_status', '<>', 'PAID');
            } elseif ($request->status === 'COMPLETED') {
                $query->where('paid_status', 'PAID');
            } else {
                $query->where('status', $request->status);
            }
        }

        $invoices = $query->paginate($limit);

        $countQuery = Invoice::where('template_name', 'office_invoice')
            ->where('status', '<>', 'DRAFT')
            ->whereCustomer($customerId);
        if ($request->has('status') && $request->status) {
            if ($request->status === 'DUE') {
                $countQuery->where('paid_status', '<>', 'PAID');
            } elseif ($request->status === 'COMPLETED') {
                $countQuery->where('paid_status', 'PAID');
            } else {
                $countQuery->where('status', $request->status);
            }
        }

        return OfficeInvoiceResource::collection($invoices)
            ->additional(['meta' => [
                'officeInvoiceTotalCount' => $countQuery->count(),
            ]]);

    }

    public function show(Company $company, $id)
    {
        $customerId = Auth::guard('customer')->id();

        $invoice = Invoice::where('template_name', 'office_invoice')
            ->where('status', '<>', 'DRAFT')
            ->whereCustomer($customerId)
            ->with(['customer', 'items', 'items.fields', 'items.fields.customField', 'taxes', 'fields', 'fields.customField', 'currency'])

            ->findOrFail($id);

        return new OfficeInvoiceResource($invoice);
    }
}
