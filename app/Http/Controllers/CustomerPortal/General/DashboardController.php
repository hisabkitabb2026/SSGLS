<?php

namespace App\Http\Controllers\CustomerPortal\General;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    public function __invoke(Request $request)
    {
        $user = Auth::guard('customer')->user();

        // Amount Due — only from office invoices (Invoice Receipts), not LR receipts
        $amountDue = Invoice::whereCustomer($user->id)
            ->where('template_name', 'office_invoice')
            ->where('status', '<>', 'DRAFT')
            ->sum('due_amount');
        $invoiceCount = Invoice::whereCustomer($user->id)
            ->where('template_name', 'office_invoice')
            ->where('status', '<>', 'DRAFT')
            ->count();
        $estimatesCount = Estimate::whereCustomer($user->id)
            ->where('status', '<>', 'DRAFT')
            ->count();
        $paymentCount = Payment::whereCustomer($user->id)
            ->count();

        // Transport KPIs
        $activeConsignments = Invoice::where('template_name', 'lr_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)
                    ->orWhere('consignee_customer_id', $user->id);
            })
            ->where('status', '<>', 'COMPLETED')
            ->count();

        $inTransit = WarehouseItem::whereHas('lr', function ($q) use ($user) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($user) {
                    $sq->where('customer_id', $user->id)
                        ->orWhere('consignee_customer_id', $user->id);
                });
        })->where('status', 'in_transit')->count();

        $delivered = WarehouseItem::whereHas('lr', function ($q) use ($user) {
            $q->where('template_name', 'lr_receipt')
                ->where('status', '<>', 'DRAFT')
                ->where(function ($sq) use ($user) {
                    $sq->where('customer_id', $user->id)
                        ->orWhere('consignee_customer_id', $user->id);
                });
        })->where('status', 'delivered')->count();

        return response()->json([
            'due_amount' => $amountDue,
            'recentInvoices' => Invoice::whereCustomer($user->id)
                ->where('template_name', 'office_invoice')
                ->where('status', '<>', 'DRAFT')
                ->take(5)->latest()->get(),
            'recentEstimates' => Estimate::whereCustomer($user->id)
                ->where('status', '<>', 'DRAFT')
                ->take(5)->latest()->get(),
            'invoice_count' => $invoiceCount,
            'estimate_count' => $estimatesCount,
            'payment_count' => $paymentCount,

            // Transport KPIs
            'active_consignments' => $activeConsignments,
            'in_transit' => $inTransit,
            'delivered' => $delivered,
        ]);
    }
}
