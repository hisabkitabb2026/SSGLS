<?php

namespace App\Http\Controllers\Pdf;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Document\EstimateService;
use App\Services\Document\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Silber\Bouncer\BouncerFacade;

class DocumentPdfController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly EstimateService $estimateService,
    ) {}

    public function invoice(Request $request, Invoice $invoice)
    {
        $user = auth()->user();
        if ($user) {
            BouncerFacade::scope()->to($invoice->company_id);
            if (! Gate::allows('view', $invoice)) {
                abort(403, 'Unauthorized.');
            }
        } else {
            $customer = auth('customer')->user();
            if ($customer) {
                if ($invoice->customer_id !== $customer->id) {
                    abort(403, 'Unauthorized.');
                }
            } else {
                abort(401, 'Unauthenticated.');
            }
        }

        if ($request->has('preview')) {
            return $this->invoiceService->getPdfData($invoice);
        }

        // When a specific copy is requested (e.g. ?copy=multi for LR Receipt
        // multi-copy PDF), bypass the cached single-copy PDF and generate a
        // fresh one so the Blade template can render all requested copies.
        if ($request->has('copy')) {
            $pdf = $this->invoiceService->getPdfData($invoice);

            return response()->make($pdf->stream(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
            ]);
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }

    public function estimate(Request $request, Estimate $estimate)
    {
        $user = auth()->user();
        if ($user) {
            BouncerFacade::scope()->to($estimate->company_id);
            if (! Gate::allows('view', $estimate)) {
                abort(403, 'Unauthorized.');
            }
        } else {
            $customer = auth('customer')->user();
            if ($customer) {
                if ($estimate->customer_id !== $customer->id) {
                    abort(403, 'Unauthorized.');
                }
            } else {
                abort(401, 'Unauthenticated.');
            }
        }

        if ($request->has('preview')) {
            return $this->estimateService->getPdfData($estimate);
        }

        return $estimate->getGeneratedPDFOrStream('estimate');
    }

    public function payment(Request $request, Payment $payment)
    {
        $user = auth()->user();
        if ($user) {
            BouncerFacade::scope()->to($payment->company_id);
            if (! Gate::allows('view', $payment)) {
                abort(403, 'Unauthorized.');
            }
        } else {
            $customer = auth('customer')->user();
            if ($customer) {
                $invoice = $payment->invoice;
                if (! $invoice || $invoice->customer_id !== $customer->id) {
                    abort(403, 'Unauthorized.');
                }
            } else {
                abort(401, 'Unauthenticated.');
            }
        }

        if ($request->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
