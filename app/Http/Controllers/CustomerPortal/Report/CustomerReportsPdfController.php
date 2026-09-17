<?php

namespace App\Http\Controllers\CustomerPortal\Report;

use App\Facades\Pdf;
use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * PDF Reports Controller for the Customer Portal (Party Portal).
 *
 * Mirrors the admin Sales report pattern (CustomerSalesReportController):
 * a left-side filter panel in the SPA builds a date-range URL, and the
 * generated PDF is streamed into an iframe on the right.
 *
 * Two reports are exposed:
 *   - LR Receipts (sales): the customer's dockets as consignor or consignee
 *   - Payments: payments made by the customer
 */
class CustomerReportsPdfController extends Controller
{
    /**
     * LR Receipts (Sales) report — PDF stream.
     */
    public function lrReceipts(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $company = $customer->company;

        [$start, $end] = $this->resolveDateRange($request, $company->id);

        $lrReceipts = Invoice::where('template_name', 'lr_receipt')
            ->where('status', '<>', 'DRAFT')
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                    ->orWhere('consignee_customer_id', $customer->id);
            })
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('invoice_date')
            ->get();

        $totalAmount = (int) $lrReceipts->sum('total');

        return $this->renderPdf(
            'app.pdf.reports.customer-lr-receipts',
            $company,
            $start,
            $end,
            [
                'receipts' => $lrReceipts,
                'totalAmount' => $totalAmount,
            ],
            $request
        );
    }

    /**
     * Payments report — PDF stream.
     */
    public function payments(Request $request)
    {
        $customer = Auth::guard('customer')->user();
        $company = $customer->company;

        [$start, $end] = $this->resolveDateRange($request, $company->id);

        $payments = Payment::whereCustomer($customer->id)
            ->whereBetween('payment_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->with('invoice')
            ->orderBy('payment_date')
            ->get();

        $totalAmount = (int) $payments->sum('amount');

        return $this->renderPdf(
            'app.pdf.reports.customer-payments',
            $company,
            $start,
            $end,
            [
                'payments' => $payments,
                'totalAmount' => $totalAmount,
            ],
            $request
        );
    }

    /**
     * Resolve the from_date/to_date query params into Carbon instances.
     */
    private function resolveDateRange(Request $request, $companyId): array
    {
        $from = $request->get('from_date') ?: now()->startOfMonth()->format('Y-m-d');
        $to = $request->get('to_date') ?: now()->endOfMonth()->format('Y-m-d');

        return [
            Carbon::createFromFormat('Y-m-d', $from),
            Carbon::createFromFormat('Y-m-d', $to),
        ];
    }

    /**
     * Shared PDF rendering — mirrors the admin report controllers.
     */
    private function renderPdf(string $view, $company, $start, $end, array $data, Request $request)
    {
        $locale = CompanySetting::getSetting('language', $company->id);
        App::setLocale($locale);

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        view()->share(array_merge($data, [
            'company' => $company,
            'from_date' => $start->translatedFormat($dateFormat),
            'to_date' => $end->translatedFormat($dateFormat),
            'currency' => $currency,
        ]));

        $pdf = Pdf::loadView($view);

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
