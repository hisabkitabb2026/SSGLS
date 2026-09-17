<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Facades\Pdf;
use App\Mail\SendPaymentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Report\ProfitLossCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PaymentService extends BaseDocumentService
{
    /**
     * Document-type configuration for SendDocumentMail.
     */
    protected array $mailType = [
        'model_class' => Payment::class,
        'data_key' => 'payment',
        'route_name' => 'payment',
        'template' => 'emails.send.payment',
        'number_field' => 'payment_number',
        'mailable_class' => SendPaymentMail::class,
    ];

    /**
     * Get the fully-qualified model class for this service.
     */
    protected function getModelClass(): string
    {
        return Payment::class;
    }

    public function __construct(
        private readonly ProfitLossCalculationService $profitLossService,
    ) {}

    public function create(Request $request): Payment
    {
        $data = $request->getPaymentPayload();

        if ($request->invoice_id) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoicePayment($request->amount);
        }

        $payment = Payment::create($data);
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);

        $serial = (new SerialNumberService)
            ->setModel($payment)
            ->setCompany($payment->company_id)
            ->setCustomer($payment->customer_id)
            ->setNextNumbers();

        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        $this->logExchangeRateIfNeeded($payment, $request->header('company'));

        $customFields = $request->customFields;

        if ($customFields) {
            $payment->addCustomFields($customFields);
        }

        // ── Recalculate P&L when payment changes invoice paid_status ──
        // When a payment is made on an Office Invoice, the paid_status may
        // change to PAID, which means amount_credit should now be populated
        // on the related LR Receipts (LR Profit becomes visible).
        $this->recalculateProfitLossForInvoice($payment->invoice_id);

        return Payment::with([
            'customer',
            'invoice',
            'paymentMethod',
            'fields',
        ])->find($payment->id);
    }

    public function update(Payment $payment, Request $request): Payment
    {
        $data = $request->getPaymentPayload();

        if ($request->invoice_id && (! $payment->invoice_id || $payment->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($request->invoice_id);
            $invoice->subtractInvoicePayment($request->amount);
        }

        if ($payment->invoice_id && (! $request->invoice_id || $payment->invoice_id !== $request->invoice_id)) {
            $invoice = Invoice::find($payment->invoice_id);
            $invoice->addInvoicePayment($payment->amount);
        }

        if ($payment->invoice_id && $payment->invoice_id === $request->invoice_id && $request->amount !== $payment->amount) {
            $invoice = Invoice::find($payment->invoice_id);
            $invoice->addInvoicePayment($payment->amount);
            $invoice->subtractInvoicePayment($request->amount);
        }

        $serial = (new SerialNumberService)
            ->setModel($payment)
            ->setCompany($payment->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($payment->id)
            ->setNextNumbers();

        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
        $payment->update($data);

        $this->logExchangeRateIfNeeded($payment, $request->header('company'));

        $customFields = $request->customFields;

        if ($customFields) {
            $payment->updateCustomFields($customFields);
        }

        // ── Recalculate P&L when payment update changes invoice paid_status ──
        $this->recalculateProfitLossForInvoice($request->invoice_id ?? $payment->invoice_id);

        return Payment::with([
            'customer',
            'invoice',
            'paymentMethod',
        ])->find($payment->id);
    }

    /**
     * Pre-deletion hook: restore invoice due amount before deleting payment.
     */
    protected function beforeDelete(Model $document): void
    {
        $payment = $document;

        if ($payment->invoice_id != null) {
            $invoice = Invoice::find($payment->invoice_id);
            $invoice->due_amount = ((int) $invoice->due_amount + (int) $payment->amount);

            if ($invoice->due_amount == $invoice->total) {
                $invoice->paid_status = Invoice::STATUS_UNPAID;
            } else {
                $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
            }

            $invoice->status = $invoice->getPreviousStatus();
            $invoice->save();

            // ── Recalculate P&L when payment deletion changes paid_status ──
            // If the invoice was PAID and now becomes UNPAID/PARTIALLY_PAID,
            // amount_credit on related LR Receipts must be cleared.
            $this->profitLossService->recalculateFromOfficeInvoice($invoice);
        }
    }

    /**
     * Recalculate P&L for the invoice associated with a payment.
     * Only triggers for Office Invoice templates (transport documents).
     */
    private function recalculateProfitLossForInvoice(?int $invoiceId): void
    {
        if (! $invoiceId) {
            return;
        }

        $invoice = Invoice::find($invoiceId);

        if ($invoice && $invoice->template_name === 'office_invoice') {
            $this->profitLossService->recalculateFromOfficeInvoice($invoice);
        }
    }

    /**
     * Prepare email send data for a payment (implements BaseDocumentService template method).
     */
    protected function prepareSendData(Model $document, array $data): array
    {
        $payment = $document;

        $data['payment'] = $payment->toArray();
        $data['user'] = $payment->customer->toArray();
        $data['company'] = Company::find($payment->company_id);
        $data['body'] = $payment->getEmailBody($data['body']);
        $data['attach']['data'] = ($payment->getEmailAttachmentSetting()) ? $this->getPdfData($payment) : null;

        return $data;
    }

    /**
     * Send a payment email — delegates to BaseDocumentService::sendDocument().
     */
    public function send(Payment $payment, array $data): array
    {
        return $this->sendDocument($payment, $data);
    }

    public function getPdfData(Payment $payment)
    {
        $company = Company::find($payment->company_id);
        $locale = CompanySetting::getSetting('language', $company->id);

        \App::setLocale($locale);

        $logo = $company->logo_path;

        view()->share([
            'payment' => $payment,
            'company_address' => $payment->getCompanyAddress(),
            'billing_address' => $payment->getCustomerBillingAddress(),
            'notes' => $payment->getNotes(),
            'logo' => $logo ?? null,
        ]);

        if (request()->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return Pdf::loadView('app.pdf.payment.payment');
    }

    public function generateFromTransaction($transaction): Payment
    {
        $invoice = Invoice::find($transaction->invoice_id);

        $serial = (new SerialNumberService)
            ->setModel(new Payment)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $data['payment_number'] = $serial->getNextNumber();
        $data['payment_date'] = Carbon::now();
        $data['amount'] = $invoice->total;
        $data['invoice_id'] = $invoice->id;
        $data['payment_method_id'] = request()->payment_method_id;
        $data['customer_id'] = $invoice->customer_id;
        $data['exchange_rate'] = $invoice->exchange_rate;
        $data['base_amount'] = $data['amount'] * $data['exchange_rate'];
        $data['currency_id'] = $invoice->currency_id;
        $data['company_id'] = $invoice->company_id;
        $data['transaction_id'] = $transaction->id;

        $payment = Payment::create($data);
        $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);
        $payment->sequence_number = $serial->nextSequenceNumber;
        $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $payment->save();

        $invoice->subtractInvoicePayment($invoice->total);

        return $payment;
    }
}
