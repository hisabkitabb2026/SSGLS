<?php

namespace App\Http\Controllers\Company\Report;

use App\Facades\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Report\ProfitLossCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ProfitLossReportController extends Controller
{
    public function __construct(
        private readonly ProfitLossCalculationService $profitLossService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * Dual-mode P&L:
     * - If the company uses transport receipt templates (lr_receipt, lorry_receipt,
     *   office_invoice), compute P&L from amount_credit/amount_debit on LR Receipts.
     * - If the company uses standard invoices only, compute P&L from invoice
     *   base_total (revenue) minus expenses (from the expenses table).
     * - If both exist, show combined P&L from both sources.
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

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id);

        $fromDate = $request->from_date;
        $toDate = $request->to_date;

        // Detect which invoice types the company uses
        $transportTemplates = ['lr_receipt', 'lorry_receipt', 'office_invoice'];
        $hasTransportReceipts = Invoice::where('company_id', $company->id)
            ->whereIn('template_name', $transportTemplates)
            ->exists();
        $hasStandardInvoices = Invoice::where('company_id', $company->id)
            ->whereNotIn('template_name', $transportTemplates)
            ->exists();

        // Initialize shared variables
        $customersData = collect();
        $grandTotalNetProfit = 0;
        $totalIncome = 0;
        $totalExpense = 0;
        $standardRevenue = 0;
        $standardExpenses = 0;
        $lrReceipts = collect();

        // ── Transport Receipt P&L (LR Receipts) ──
        if ($hasTransportReceipts) {
            $lrReceipts = Invoice::with(['customer', 'consigneeCustomer', 'ownerCustomer', 'driverCustomer', 'brokerCustomer'])
                ->where('company_id', $company->id)
                ->where('template_name', 'lr_receipt')
                ->when($fromDate, fn ($query, $date) => $query->where('invoice_date', '>=', $date))
                ->when($toDate, fn ($query, $date) => $query->where('invoice_date', '<=', $date))
                ->when($request->customer_id, function ($query) use ($request, $company) {
                    $customer = Customer::find($request->customer_id);
                    if ($customer) {
                        $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customer->name));
                        $allIds = Customer::where('customers.company_id', $company->id)
                            ->get(['id', 'name'])
                            ->filter(function ($c) use ($normalizedName) {
                                return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c->name)) === $normalizedName;
                            })
                            ->pluck('id')
                            ->toArray();

                        $query->where(function ($q) use ($allIds) {
                            $q->whereIn('customer_id', $allIds)
                                ->orWhereIn('consignee_customer_id', $allIds);
                        });
                    }
                })
                ->when($request->customer_name, function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->whereHas('customer', function ($sub) use ($request) {
                            $sub->where('name', 'LIKE', '%'.$request->customer_name.'%');
                        })->orWhereHas('consigneeCustomer', function ($sub) use ($request) {
                            $sub->where('name', 'LIKE', '%'.$request->customer_name.'%');
                        });
                    });
                })
                ->get();

            // Group LR receipts by customer based on GST Tax Payable By field
            $customerProfitLoss = [];

            foreach ($lrReceipts as $lrReceipt) {
                $gstTaxPayableBy = $lrReceipt->getCustomFieldValueBySlug('gst-tax-payable-by');

                $payingCustomerType = strtoupper($gstTaxPayableBy ?? '') === 'CONSIGNEE' ? 'CONSIGNEE' : 'CONSIGNOR';

                $customerId = null;
                $customerModel = null;
                if ($payingCustomerType === 'CONSIGNEE' && $lrReceipt->consignee_customer_id) {
                    $customerId = $lrReceipt->consignee_customer_id;
                    $customerModel = $lrReceipt->consigneeCustomer;
                } else {
                    $customerId = $lrReceipt->customer_id;
                    $customerModel = $lrReceipt->customer;
                }

                if (! $customerId) {
                    continue;
                }

                $amountCredit = (float) $lrReceipt->amount_credit;
                $amountDebit = (float) $lrReceipt->amount_debit;
                $netProfit = $amountCredit - $amountDebit;

                // Use already-loaded customer relationship instead of N+1 query
                $customerName = $customerModel?->name ?? 'Unknown';

                if (! isset($customerProfitLoss[$customerId])) {
                    $customerProfitLoss[$customerId] = [
                        'id' => $customerId,
                        'name' => $customerName,
                        'lrReceipts' => [],
                        'totalIncome' => 0,
                        'totalNetProfit' => 0,
                    ];
                }

                $customerProfitLoss[$customerId]['lrReceipts'][] = [
                    'lr_no' => $lrReceipt->invoice_number,
                    'lr_date' => $lrReceipt->invoice_date ? Carbon::parse($lrReceipt->invoice_date)->format('Y-m-d') : '',
                    'amount_credit' => $amountCredit,
                    'amount_credit_date' => $lrReceipt->amount_credit_date,
                    'amount_debit' => $amountDebit,
                    'amount_debit_date' => $lrReceipt->amount_debit_date,
                    'income' => $amountCredit,
                    'net_profit' => $netProfit,
                    'office_invoice_no' => $lrReceipt->office_invoice_number,
                    'challan_no' => $lrReceipt->challan_number,
                ];

                $customerProfitLoss[$customerId]['totalIncome'] += $amountCredit;
                $customerProfitLoss[$customerId]['totalNetProfit'] += $netProfit;

                $grandTotalNetProfit += $netProfit;
                $totalIncome += $amountCredit;
                $totalExpense += $amountDebit;
            }

            $customersData = collect($customerProfitLoss)->values();
        }

        // ── Standard Invoice P&L ──
        if ($hasStandardInvoices) {
            // Revenue from standard invoices (base_total)
            $standardRevenueQuery = Invoice::where('company_id', $company->id)
                ->whereNotIn('template_name', $transportTemplates)
                ->when($fromDate, fn ($query, $date) => $query->where('invoice_date', '>=', $date))
                ->when($toDate, fn ($query, $date) => $query->where('invoice_date', '<=', $date))
                ->when($request->customer_id, fn ($query, $id) => $query->where('customer_id', $id))
                ->when($request->customer_name, function ($query) use ($request) {
                    $query->whereHas('customer', function ($sub) use ($request) {
                        $sub->where('name', 'LIKE', '%'.$request->customer_name.'%');
                    });
                });

            $standardRevenue = (float) $standardRevenueQuery->sum('base_total');

            // Expenses from the expenses table
            $standardExpensesQuery = Expense::where('company_id', $company->id)
                ->when($fromDate, fn ($query, $date) => $query->where('expense_date', '>=', $date))
                ->when($toDate, fn ($query, $date) => $query->where('expense_date', '<=', $date));

            $standardExpenses = (float) $standardExpensesQuery->sum('base_amount');

            $totalIncome += $standardRevenue;
            $totalExpense += $standardExpenses;
            $grandTotalNetProfit += ($standardRevenue - $standardExpenses);
        }

        $from_date = Carbon::createFromFormat('Y-m-d', $fromDate)->translatedFormat($dateFormat);
        $to_date = Carbon::createFromFormat('Y-m-d', $toDate)->translatedFormat($dateFormat);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        view()->share([
            'company' => $company,
            'customersData' => $customersData,
            'grandTotalNetProfit' => $grandTotalNetProfit,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
            'income' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $grandTotalNetProfit,
            'lrReceipts' => $lrReceipts,
            'hasTransportReceipts' => $hasTransportReceipts,
            'hasStandardInvoices' => $hasStandardInvoices,
            'standardRevenue' => $standardRevenue,
            'standardExpenses' => $standardExpenses,
        ]);

        $pdf = Pdf::loadView('app.pdf.reports.profit-loss');

        if ($request->has('preview')) {
            return view('app.pdf.reports.profit-loss');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
