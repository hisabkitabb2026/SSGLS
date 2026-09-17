<?php

namespace App\Http\Controllers\Company\Report;

use App\Facades\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CustomerNameSalesReportController extends Controller
{
    /**
     * Handle the incoming request.
     * Sales report filtered by customer name (text search, not ID).
     *
     * @param  string  $hash
     * @return JsonResponse
     */
    public function __invoke(Request $request, $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();

        $this->authorize('view report', $company);

        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $start = Carbon::createFromFormat('Y-m-d', $request->from_date);
        $end = Carbon::createFromFormat('Y-m-d', $request->to_date);

        $customerName = $request->input('customer_name', '');

        // Transport receipt templates use amount_credit as the sales amount
        // instead of base_total (which is 0 for transport receipts).
        $transportTemplates = ['lr_receipt', 'lorry_receipt', 'office_invoice'];

        // Find customers matching the name search (LIKE match)
        $customers = Customer::where('company_id', $company->id)
            ->where('name', 'LIKE', '%'.$customerName.'%')
            ->with(['invoices' => function ($query) use ($start, $end) {
                $query->whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                );
            }])
            ->get();

        // Also search consignee customers for transport receipt templates
        $consigneeInvoices = Invoice::where('company_id', $company->id)
            ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->whereHas('consigneeCustomer', function ($query) use ($customerName) {
                $query->where('name', 'LIKE', '%'.$customerName.'%');
            })
            ->with(['customer', 'consigneeCustomer'])
            ->get();

        $totalAmount = 0;

        // Process standard customer invoices
        foreach ($customers as $customer) {
            $customerTotalAmount = 0;
            foreach ($customer->invoices as $invoice) {
                if (in_array($invoice->template_name, $transportTemplates)) {
                    $customerTotalAmount += (float) ($invoice->amount_credit ?? 0);
                } else {
                    $customerTotalAmount += $invoice->base_total;
                }
            }
            $customer->totalAmount = $customerTotalAmount;
            $totalAmount += $customerTotalAmount;
        }

        // Filter out customers with no invoices in the date range
        $customers = $customers->filter(function ($customer) {
            return $customer->invoices->count() > 0;
        });

        // Process consignee-matched invoices (transport receipts)
        $consigneeCustomers = [];
        foreach ($consigneeInvoices as $invoice) {
            $consignee = $invoice->consigneeCustomer;
            if (! $consignee) {
                continue;
            }

            $consigneeId = $consignee->id;
            if (! isset($consigneeCustomers[$consigneeId])) {
                $consigneeCustomers[$consigneeId] = [
                    'id' => $consigneeId,
                    'name' => $consignee->name,
                    'invoices' => collect(),
                    'totalAmount' => 0,
                ];
            }

            $amount = in_array($invoice->template_name, $transportTemplates)
                ? (float) ($invoice->amount_credit ?? 0)
                : $invoice->base_total;

            $consigneeCustomers[$consigneeId]['invoices']->push($invoice);
            $consigneeCustomers[$consigneeId]['totalAmount'] += $amount;
            $totalAmount += $amount;
        }

        // Convert consignee customers to a collection of objects for the view
        $consigneeCustomers = collect($consigneeCustomers)->map(function ($item) {
            return (object) $item;
        })->values();

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id);
        $from_date = Carbon::createFromFormat('Y-m-d', $request->from_date)->translatedFormat($dateFormat);
        $to_date = Carbon::createFromFormat('Y-m-d', $request->to_date)->translatedFormat($dateFormat);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        $colors = [
            'primary_text_color',
            'heading_text_color',
            'section_heading_text_color',
            'border_color',
            'body_text_color',
            'footer_text_color',
            'footer_total_color',
            'footer_bg_color',
            'date_text_color',
        ];

        $colorSettings = CompanySetting::whereIn('option', $colors)
            ->whereCompany($company->id)
            ->get();

        view()->share([
            'customers' => $customers,
            'consigneeCustomers' => $consigneeCustomers,
            'totalAmount' => $totalAmount,
            'colorSettings' => $colorSettings,
            'company' => $company,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
            'searchedName' => $customerName,
        ]);

        $pdf = Pdf::loadView('app.pdf.reports.sales-customer-name');

        if ($request->has('preview')) {
            return view('app.pdf.reports.sales-customer-name');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
