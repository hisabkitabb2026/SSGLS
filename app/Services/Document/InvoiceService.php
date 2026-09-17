<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Mail\SendDocumentMail;
use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomFieldValue;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Report\ProfitLossCalculationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceService extends BaseDocumentService
{
    /**
     * Document-type configuration for SendDocumentMail.
     */
    protected array $mailType = [
        'model_class' => Invoice::class,
        'data_key' => 'invoice',
        'route_name' => 'invoice',
        'template' => 'emails.send.invoice',
        'number_field' => 'invoice_number',
        'mailable_class' => SendInvoiceMail::class,
    ];

    /**
     * Get the fully-qualified model class for this service.
     */
    protected function getModelClass(): string
    {
        return Invoice::class;
    }

    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly ProfitLossCalculationService $profitLossService,
        private readonly TransportDocumentService $transportDocumentService,
    ) {}

    public function create(Request $request): Invoice
    {
        $data = $request->getInvoicePayload();

        if ($request->has('invoiceSend')) {
            $data['status'] = Invoice::STATUS_SENT;
        }

        $invoice = Invoice::create($data);

        // Always generate sequence numbers for ordering
        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setTemplateName($invoice->template_name)
            ->setNextNumbers();

        $invoice->sequence_number = $serial->nextSequenceNumber;

        // Only auto-generate invoice_number if not provided by user
        if (! $request->has('invoice_number') || empty($request->invoice_number)) {
            $invoice->invoice_number = $serial->getNextNumber();
        }

        $invoice->customer_sequence_number = null;
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        // Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
        // have their own item/tax creation logic in TransportDocumentService.
        // For standard invoices/estimates, create items and taxes here.
        if (! $this->transportDocumentService->isTransportTemplate($invoice)) {
            if ($request->has('items') && ! empty($request->items)) {
                $validItems = array_filter(
                    $request->items,
                    fn ($item) => ! empty($item['name'])
                );

                if (! empty($validItems)) {
                    $this->documentItemService->createItems($invoice, $validItems);
                }
            }

            if ($request->has('taxes') && (! empty($request->taxes))) {
                $this->documentItemService->createTaxes($invoice, $request->taxes);
            }
        }

        $this->logExchangeRateIfNeeded($invoice, $request->header('company'));

        // Handle transport documents (lr_receipt, lorry_receipt, office_invoice)
        if ($this->transportDocumentService->isTransportTemplate($invoice)) {
            $this->transportDocumentService->createItems($invoice, $request);
            $this->transportDocumentService->createTaxes($invoice, $request);
            $this->transportDocumentService->recalculateProfitLoss($invoice);
        }

        if ($request->customFields) {
            $invoice->addCustomFields($request->customFields);
        }

        // Recalculate profit/loss for Office Invoice and Lorry Receipt
        if ($invoice->template_name === 'office_invoice') {
            $this->profitLossService->recalculateFromOfficeInvoice($invoice);
        } elseif ($invoice->template_name === 'lorry_receipt') {
            $this->profitLossService->recalculateFromLorryReceipt($invoice);
        }

        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'consigneeCustomer',
            'taxes',
            'fields',
            'fields.customField',
        ])->find($invoice->id);
    }

    /**
     * @throws ValidationException
     */
    public function update(Invoice $invoice, Request $request): Invoice
    {
        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($request->customer_id)
            ->setTemplateName($invoice->template_name)
            ->setModelObject($invoice->id)
            ->setNextNumbers();

        $data = $request->getInvoicePayload();
        $oldTotal = $invoice->total;

        $totalPaidAmount = $invoice->total - $invoice->due_amount;

        if ($totalPaidAmount > 0 && $invoice->customer_id !== $request->customer_id) {
            throw ValidationException::withMessages([
                'customer_id' => ['customer_cannot_be_changed_after_payment_is_added'],
            ]);
        }

        if ($data['total'] >= 0 && $data['total'] < $totalPaidAmount) {
            throw ValidationException::withMessages([
                'total' => ['total_invoice_amount_must_be_more_than_paid_amount'],
            ]);
        }

        if ($oldTotal != $data['total']) {
            $oldTotal = (int) round($data['total']) - (int) $oldTotal;
        } else {
            $oldTotal = 0;
        }

        $data['due_amount'] = ($invoice->due_amount + $oldTotal);
        $data['base_due_amount'] = $data['due_amount'] * $data['exchange_rate'];
        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $invoice->update($data);

        $statusData = $invoice->getInvoiceStatusByAmount($data['due_amount']);
        if (! empty($statusData)) {
            $invoice->update($statusData);
        }

        $this->logExchangeRateIfNeeded($invoice, $request->header('company'));

        $itemIds = $invoice->items()->pluck('id');
        CustomFieldValue::where('custom_field_valuable_type', (new InvoiceItem)->getMorphClass())
            ->whereIn('custom_field_valuable_id', $itemIds)
            ->delete();

        $invoice->items()->delete();
        $invoice->taxes()->delete();

        // Transport receipt templates have their own item/tax creation logic
        // in TransportDocumentService. For standard invoices/estimates, create
        // items and taxes here.
        if (! $this->transportDocumentService->isTransportTemplate($invoice)) {
            if ($request->has('items') && ! empty($request->items)) {
                $validItems = array_filter(
                    $request->items,
                    fn ($item) => ! empty($item['name'])
                );

                if (! empty($validItems)) {
                    $this->documentItemService->createItems($invoice, $validItems);
                }
            }

            if ($request->has('taxes') && (! empty($request->taxes))) {
                $this->documentItemService->createTaxes($invoice, $request->taxes);
            }
        }

        // Handle transport documents (lr_receipt, lorry_receipt, office_invoice)
        if ($this->transportDocumentService->isTransportTemplate($invoice)) {
            $this->transportDocumentService->updateItems($invoice, $request);
            $this->transportDocumentService->createTaxes($invoice, $request);
            $this->transportDocumentService->recalculateProfitLoss($invoice);
        }

        if ($request->customFields) {
            $invoice->updateCustomFields($request->customFields);
        }

        // Recalculate profit/loss for Office Invoice and Lorry Receipt on update
        if ($invoice->template_name === 'office_invoice') {
            $this->profitLossService->recalculateFromOfficeInvoice($invoice);
        } elseif ($invoice->template_name === 'lorry_receipt') {
            $this->profitLossService->recalculateFromLorryReceipt($invoice);
        }

        return Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'consigneeCustomer',
            'taxes',
            'fields',
            'fields.customField',
        ])->find($invoice->id);

    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $invoice = Invoice::find($id);

            if ($invoice->transactions()->exists()) {
                $invoice->transactions()->delete();
            }

            // ── Clean up P&L data when deleting transport documents ──
            // When an Office Invoice is deleted, clear amount_credit and
            // amount_debit on related LR Receipts (they were populated from
            // the Office Invoice's item amounts). Also recalculate amount_debit
            // on remaining LR Receipts since the income distribution changed.
            //
            // When a Lorry Receipt is deleted, clear amount_debit on related
            // LR Receipts (they were populated from the Lorry Receipt's
            // Section C + Section E amounts).
            if ($invoice->template_name === 'office_invoice') {
                $this->cleanupProfitLossOnOfficeInvoiceDelete($invoice);
            } elseif ($invoice->template_name === 'lorry_receipt') {
                $this->cleanupProfitLossOnLorryReceiptDelete($invoice);
            }

            $invoice->delete();
        }

        return true;
    }

    /**
     * Clear amount_credit and amount_debit on LR Receipts that were linked
     * to the deleted Office Invoice, then recalculate amount_debit for any
     * remaining Lorry Receipts that reference those dockets.
     */
    private function cleanupProfitLossOnOfficeInvoiceDelete(Invoice $officeInvoice): void
    {
        $items = $officeInvoice->items()->whereNotNull('consignment_number')->get();

        foreach ($items as $item) {
            $docketNumber = $item->consignment_number;

            $lrReceipt = Invoice::where('template_name', 'lr_receipt')
                ->where('company_id', $officeInvoice->company_id)
                ->where('invoice_number', $docketNumber)
                ->first();

            if ($lrReceipt) {
                $lrReceipt->amount_credit = 0;
                $lrReceipt->amount_debit = 0;
                $lrReceipt->office_invoice_number = null;
                $lrReceipt->challan_number = null;
                $lrReceipt->save();
            }
        }
    }

    /**
     * Clear amount_debit on LR Receipts that were linked to the deleted
     * Lorry Receipt (via received_no_bilties docket numbers).
     */
    private function cleanupProfitLossOnLorryReceiptDelete(Invoice $lorryReceipt): void
    {
        $docketNumbers = array_filter(
            array_map('trim', explode(',', $lorryReceipt->received_no_bilties ?? '')),
            fn ($value) => ! empty($value)
        );

        if (empty($docketNumbers)) {
            return;
        }

        Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $lorryReceipt->company_id)
            ->whereIn('invoice_number', $docketNumbers)
            ->update([
                'amount_debit' => 0,
                'challan_number' => null,
            ]);
    }

    /**
     * Prepare email send data for an invoice (implements BaseDocumentService template method).
     */
    protected function prepareSendData(Model $document, array $data): array
    {
        $invoice = $document;

        $data['invoice'] = $invoice->toArray();
        $data['customer'] = $invoice->customer->toArray();
        $data['company'] = Company::find($invoice->company_id);
        $data['subject'] = $invoice->getEmailString($data['subject']);
        $data['body'] = $invoice->getEmailString($data['body']);
        $data['attach']['data'] = ($invoice->getEmailAttachmentSetting()) ? $this->getPdfData($invoice) : null;

        return $data;
    }

    public function preview(Invoice $invoice, array $data): array
    {
        $data = $this->prepareSendData($invoice, $data);

        return [
            'type' => 'preview',
            'view' => new SendDocumentMail($data, $this->mailType),
        ];
    }

    /**
     * Send an invoice email — delegates to BaseDocumentService::sendDocument().
     * Updates status to SENT after sending if currently DRAFT.
     */
    public function send(Invoice $invoice, array $data): array
    {
        $result = $this->sendDocument($invoice, $data);

        if ($invoice->status == Invoice::STATUS_DRAFT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        }

        return $result;
    }

    public function getPdfData(Invoice $invoice)
    {
        $taxes = $this->aggregateItemTaxes($invoice);

        $invoiceTemplate = Invoice::find($invoice->id)->template_name;

        return $this->preparePdfView(
            $invoice,
            [
                'invoice' => $invoice,
                'taxes' => $taxes,
            ],
            'invoice',
            $invoiceTemplate,
        );
    }

    public function clone(Invoice $invoice): Invoice
    {
        $date = Carbon::now();

        $serial = (new SerialNumberService)
            ->setModel($invoice)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setTemplateName($invoice->template_name)
            ->setNextNumbers();

        $dueDate = null;
        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $invoice->company_id
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $invoice->company_id
            ));
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $exchangeRate = $invoice->exchange_rate;

        $newInvoice = Invoice::create([
            'invoice_date' => $date->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $invoice->reference_number,
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->template_name,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => $invoice->discount_val,
            'total' => $invoice->total,
            'due_amount' => $invoice->total,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => $invoice->tax,
            'notes' => $invoice->notes,
            'exchange_rate' => $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            'base_due_amount' => $invoice->total * $exchangeRate,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $newInvoice->unique_hash = Hashids::connection(Invoice::class)->encode($newInvoice->id);
        $newInvoice->save();

        $invoice->load('items.taxes');
        $this->documentItemService->createItems($newInvoice, $invoice->items->toArray());

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($newInvoice, $invoice->taxes->toArray());
        }

        $this->copyCustomFields($invoice, $newInvoice);

        return $newInvoice;
    }

    public function convertToEstimate(Invoice $invoice): Estimate
    {
        $invoice->load(['items', 'items.taxes', 'customer', 'taxes']);

        $serial = (new SerialNumberService)
            ->setModel(new Estimate)
            ->setCompany($invoice->company_id)
            ->setCustomer($invoice->customer_id)
            ->setNextNumbers();

        $exchangeRate = $invoice->exchange_rate;

        $estimate = Estimate::create([
            'creator_id' => $invoice->creator_id,
            'estimate_date' => Carbon::now()->format('Y-m-d'),
            'expiry_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $serial->getNextNumber(),
            'customer_id' => $invoice->customer_id,
            'company_id' => $invoice->company_id,
            'template_name' => $invoice->getEstimateTemplateName(),
            'status' => Estimate::STATUS_DRAFT,
            'sub_total' => $invoice->sub_total,
            'discount' => $invoice->discount,
            'discount_type' => $invoice->discount_type,
            'discount_val' => $invoice->discount_val,
            'total' => $invoice->total,
            'tax_per_item' => $invoice->tax_per_item,
            'discount_per_item' => $invoice->discount_per_item,
            'tax' => $invoice->tax,
            'notes' => $invoice->notes,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => $invoice->discount_val * $exchangeRate,
            'base_sub_total' => $invoice->sub_total * $exchangeRate,
            'base_total' => $invoice->total * $exchangeRate,
            'base_tax' => $invoice->tax * $exchangeRate,
            'currency_id' => $invoice->currency_id,
            'sales_tax_type' => $invoice->sales_tax_type,
            'sales_tax_address_type' => $invoice->sales_tax_address_type,
        ]);

        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $estimate->save();

        $this->documentItemService->createItems($estimate, $invoice->items->toArray());

        if ($invoice->taxes) {
            $this->documentItemService->createTaxes($estimate, $invoice->taxes->toArray());
        }

        $this->copyCustomFields($invoice, $estimate);

        return $estimate;
    }

    public function changeStatus(Invoice $invoice, string $status): void
    {
        if ($status == Invoice::STATUS_SENT) {
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        } elseif ($status == Invoice::STATUS_COMPLETED) {
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->due_amount = 0;
            $invoice->save();
        }
    }
}
