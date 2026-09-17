<?php

namespace App\Services\Document;

use App\Models\CustomFieldValue;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Report\ProfitLossCalculationService;
use Illuminate\Http\Request;

class TransportDocumentService
{
    public function __construct(
        private readonly DocumentItemService $documentItemService,
        private readonly ProfitLossCalculationService $profitLossService,
    ) {}

    /**
     * Determine if an invoice uses a transport template
     */
    public function isTransportTemplate(Invoice $invoice): bool
    {
        return in_array(
            $invoice->template_name,
            ['lorry_receipt', 'office_invoice', 'lr_receipt']
        );
    }

    /**
     * Create items for transport documents (handling both regular items and custom fields)
     * Transport templates may not send items — only create if present
     */
    public function createItems(Invoice $invoice, Request $request): void
    {
        if ($request->has('items') && ! empty($request->items)) {
            $validItems = array_filter(
                $request->items,
                fn ($item) => ! empty($item['name']) || ! empty($item['consignment_number'])
            );

            if (! empty($validItems)) {
                $this->documentItemService->createItems($invoice, $validItems);
            }
        }
    }

    /**
     * Update items for transport documents
     */
    public function updateItems(Invoice $invoice, Request $request): void
    {
        $itemIds = $invoice->items()->pluck('id');
        CustomFieldValue::where('custom_field_valuable_type', (new InvoiceItem)->getMorphClass())
            ->whereIn('custom_field_valuable_id', $itemIds)
            ->delete();

        $invoice->items()->delete();

        $this->createItems($invoice, $request);
    }

    /**
     * Create taxes for transport documents
     */
    public function createTaxes(Invoice $invoice, Request $request): void
    {
        if ($request->has('taxes') && ! empty($request->taxes)) {
            $this->documentItemService->createTaxes($invoice, $request->taxes);
        }
    }

    /**
     * Recalculate profit/loss for transport templates
     */
    public function recalculateProfitLoss(Invoice $invoice): void
    {
        if ($invoice->template_name === 'office_invoice') {
            $this->profitLossService->recalculateFromOfficeInvoice($invoice);
        } elseif ($invoice->template_name === 'lorry_receipt') {
            $this->profitLossService->recalculateFromLorryReceipt($invoice);
        }
    }
}
