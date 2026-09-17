<?php

namespace App\Services\Document;

use App\Facades\Hashids;
use App\Mail\SendEstimateMail;
use App\Models\CompanySetting;
use App\Models\CustomFieldValue;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstimateService extends BaseDocumentService
{
    /**
     * Document-type configuration for SendDocumentMail.
     */
    protected array $mailType = [
        'model_class' => Estimate::class,
        'data_key' => 'estimate',
        'route_name' => 'estimate',
        'template' => 'emails.send.estimate',
        'number_field' => 'estimate_number',
        'mailable_class' => SendEstimateMail::class,
    ];

    /**
     * Get the fully-qualified model class for this service.
     */
    protected function getModelClass(): string
    {
        return Estimate::class;
    }

    public function __construct(
        private readonly DocumentItemService $documentItemService,
    ) {}

    public function create(Request $request): Estimate
    {
        $data = $request->getEstimatePayload();

        if ($request->has('estimateSend')) {
            $data['status'] = Estimate::STATUS_SENT;
        }

        $estimate = Estimate::create($data);
        $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $estimate->sequence_number = $serial->nextSequenceNumber;
        $estimate->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $estimate->save();

        $this->logExchangeRateIfNeeded($estimate, $request->header('company'));

        // Save quotation stations if provided
        if ($request->has('quotation_stations') && ! empty($request->quotation_stations)) {
            $this->saveQuotationStations($estimate, $request->quotation_stations);
        }

        // Transform rates data to rate_card for quotation PDFs
        $items = $this->transformQuotationRatesToRateCard($request->items, $estimate->company_id);

        $this->documentItemService->createItems($estimate, $items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($estimate, $request->taxes);
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $estimate->addCustomFields($customFields);
        }

        return $estimate;
    }

    /**
     * Save quotation stations and their rates to the database.
     */
    private function saveQuotationStations(Estimate $estimate, array $stations): void
    {
        foreach ($stations as $sequence => $stationData) {
            if (empty($stationData['name'])) {
                continue;
            }

            $station = $estimate->quotationStations()->create([
                'name' => $stationData['name'],
                'sequence' => $sequence + 1,
            ]);

            if (isset($stationData['rates']) && is_array($stationData['rates'])) {
                foreach ($stationData['rates'] as $rate) {
                    $station->rates()->create([
                        'capacity' => $rate['capacity'] ?? '',
                        'rate' => $rate['rate'] ?? 0,
                    ]);
                }
            }
        }
    }

    /**
     * Transform quotation rates data to rate_card format for PDF pivot table rendering.
     * Converts capacity-rate pairs to rate_card using unit IDs as keys.
     */
    private function transformQuotationRatesToRateCard(array $items, int $companyId): array
    {
        // Get all units for this company
        $units = Unit::where('company_id', $companyId)
            ->get()
            ->keyBy('name');

        return collect($items)->map(function ($item) use ($units) {
            // If item has rates (from quotation_stations), transform to rate_card
            if (isset($item['rates']) && is_array($item['rates']) && ! empty($item['rates'])) {
                $rateCard = [];
                foreach ($item['rates'] as $rate) {
                    $capacity = $rate['capacity'] ?? null;
                    $rateValue = $rate['rate'] ?? 0;

                    // Find unit by capacity name (e.g., "9MT", "10MT")
                    if ($capacity && isset($units[$capacity])) {
                        $rateCard[$units[$capacity]->id] = $rateValue;
                    }
                }
                $item['rate_card'] = $rateCard;
                unset($item['rates']); // Remove rates as we've converted to rate_card
            }

            return $item;
        })->toArray();
    }

    public function update(Estimate $estimate, Request $request): Estimate
    {
        $data = $request->getEstimatePayload();

        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($request->customer_id)
            ->setModelObject($estimate->id)
            ->setNextNumbers();

        $data['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

        $estimate->update($data);

        $this->logExchangeRateIfNeeded($estimate, $request->header('company'));

        $itemIds = $estimate->items()->pluck('id');
        CustomFieldValue::where('custom_field_valuable_type', (new EstimateItem)->getMorphClass())
            ->whereIn('custom_field_valuable_id', $itemIds)
            ->delete();

        $estimate->items()->delete();
        $estimate->taxes()->delete();
        $estimate->quotationStations()->delete();

        // Save quotation stations if provided
        if ($request->has('quotation_stations') && ! empty($request->quotation_stations)) {
            $this->saveQuotationStations($estimate, $request->quotation_stations);
        }

        // Transform rates data to rate_card for quotation PDFs
        $items = $this->transformQuotationRatesToRateCard($request->items, $estimate->company_id);

        $this->documentItemService->createItems($estimate, $items);

        if ($request->has('taxes') && (! empty($request->taxes))) {
            $this->documentItemService->createTaxes($estimate, $request->taxes);
        }

        if ($request->customFields) {
            $estimate->updateCustomFields($request->customFields);
        }

        $relations = [
            'items.taxes',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
        ];

        // Include quotationStations for quotations
        if ($estimate->estimate_type === 'quotation') {
            $relations[] = 'quotationStations.rates';
        }

        return Estimate::with($relations)->find($estimate->id);
    }

    /**
     * Prepare email send data for an estimate (implements BaseDocumentService template method).
     */
    protected function prepareSendData(Model $document, array $data): array
    {
        $estimate = $document;

        $data['estimate'] = $estimate->toArray();
        $data['user'] = $estimate->customer->toArray();
        $data['company'] = $estimate->company->toArray();
        $data['body'] = $estimate->getEmailBody($data['body']);
        $data['attach']['data'] = ($estimate->getEmailAttachmentSetting()) ? $this->getPdfData($estimate) : null;

        return $data;
    }

    /**
     * Send an estimate email — delegates to BaseDocumentService::sendDocument().
     * Updates status to SENT before sending if currently DRAFT.
     */
    public function send(Estimate $estimate, array $data): array
    {
        if ($estimate->status == Estimate::STATUS_DRAFT) {
            $estimate->status = Estimate::STATUS_SENT;
            $estimate->save();
        }

        return $this->sendDocument($estimate, $data);
    }

    public function getPdfData(Estimate $estimate)
    {
        $taxes = $this->aggregateItemTaxes($estimate);

        // Determine if this is a quotation by checking estimate_type column
        $isQuotation = $estimate->estimate_type === 'quotation';

        $templateFolder = $isQuotation ? 'quotation' : 'estimate';
        $estimateTemplate = $isQuotation ? 'quotation1' : (Estimate::find($estimate->id)->template_name ?? 'estimate1');

        if ($isQuotation) {
            // For quotations: Get unique capacities from quotation_rates as "units"
            // Each capacity becomes a column header (9MT, 10MT, 12MT, etc.)
            $capacities = $estimate->quotationStations()
                ->with('rates')
                ->get()
                ->flatMap(fn ($station) => $station->rates)
                ->unique('capacity')
                ->pluck('capacity')
                ->values()
                ->map(fn ($capacity, $index) => (object) [
                    'id' => $index,
                    'name' => $capacity,
                ])
                ->sortBy('name');
            $units = $capacities;
        } else {
            // For regular estimates: no units needed, items use quantity/price format
            $units = collect();
        }

        return $this->preparePdfView(
            $estimate,
            [
                'estimate' => $estimate,
                'taxes' => $taxes,
                'units' => $units,
            ],
            $templateFolder,
            $estimateTemplate,
        );
    }

    public function clone(Estimate $estimate): Estimate
    {
        $date = Carbon::now();

        $serial = (new SerialNumberService)
            ->setModel($estimate)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $expiryDate = null;
        $expiryEnabled = CompanySetting::getSetting(
            'estimate_set_expiry_date_automatically',
            $estimate->company_id
        );

        if ($expiryEnabled === 'YES') {
            $expiryDays = intval(CompanySetting::getSetting(
                'estimate_expiry_date_days',
                $estimate->company_id
            ));
            $expiryDate = Carbon::now()->addDays($expiryDays)->format('Y-m-d');
        }

        $exchangeRate = $estimate->exchange_rate;

        $newEstimate = Estimate::create([
            'estimate_date' => $date->format('Y-m-d'),
            'expiry_date' => $expiryDate,
            'estimate_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $estimate->reference_number,
            'customer_id' => $estimate->customer_id,
            'company_id' => $estimate->company_id,
            'template_name' => $estimate->template_name,
            'status' => Estimate::STATUS_DRAFT,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'due_amount' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'exchange_rate' => $exchangeRate,
            'base_total' => $estimate->total * $exchangeRate,
            'base_discount_val' => $estimate->discount_val * $exchangeRate,
            'base_sub_total' => $estimate->sub_total * $exchangeRate,
            'base_tax' => $estimate->tax * $exchangeRate,
            'base_due_amount' => $estimate->total * $exchangeRate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $newEstimate->unique_hash = Hashids::connection(Estimate::class)->encode($newEstimate->id);
        $newEstimate->save();

        $estimate->load('items.taxes');
        $this->documentItemService->createItems($newEstimate, $estimate->items->toArray());

        if ($estimate->taxes) {
            $this->documentItemService->createTaxes($newEstimate, $estimate->taxes->toArray());
        }

        $this->copyCustomFields($estimate, $newEstimate);

        return $newEstimate;
    }

    public function convertToInvoice(Estimate $estimate): Invoice
    {
        $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

        $invoiceDate = Carbon::now();
        $dueDate = null;

        $dueDateEnabled = CompanySetting::getSetting(
            'invoice_set_due_date_automatically',
            $estimate->company_id
        );

        if ($dueDateEnabled === 'YES') {
            $dueDateDays = intval(CompanySetting::getSetting(
                'invoice_due_date_days',
                $estimate->company_id
            ));
            $dueDate = Carbon::now()->addDays($dueDateDays)->format('Y-m-d');
        }

        $serial = (new SerialNumberService)
            ->setModel(new Invoice)
            ->setCompany($estimate->company_id)
            ->setCustomer($estimate->customer_id)
            ->setNextNumbers();

        $templateName = $estimate->getInvoiceTemplateName();
        $exchangeRate = $estimate->exchange_rate;

        $invoice = Invoice::create([
            'creator_id' => Auth::id(),
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $dueDate,
            'invoice_number' => $serial->getNextNumber(),
            'sequence_number' => $serial->nextSequenceNumber,
            'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
            'reference_number' => $serial->getNextNumber(),
            'customer_id' => $estimate->customer_id,
            'company_id' => $estimate->company_id,
            'template_name' => $templateName,
            'status' => Invoice::STATUS_DRAFT,
            'paid_status' => Invoice::STATUS_UNPAID,
            'sub_total' => $estimate->sub_total,
            'discount' => $estimate->discount,
            'discount_type' => $estimate->discount_type,
            'discount_val' => $estimate->discount_val,
            'total' => $estimate->total,
            'due_amount' => $estimate->total,
            'tax_per_item' => $estimate->tax_per_item,
            'discount_per_item' => $estimate->discount_per_item,
            'tax' => $estimate->tax,
            'notes' => $estimate->notes,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => $estimate->discount_val * $exchangeRate,
            'base_sub_total' => $estimate->sub_total * $exchangeRate,
            'base_total' => $estimate->total * $exchangeRate,
            'base_tax' => $estimate->tax * $exchangeRate,
            'currency_id' => $estimate->currency_id,
            'sales_tax_type' => $estimate->sales_tax_type,
            'sales_tax_address_type' => $estimate->sales_tax_address_type,
        ]);

        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
        $invoice->save();

        $this->documentItemService->createItems($invoice, $estimate->items->toArray());

        if ($estimate->taxes) {
            $this->documentItemService->createTaxes($invoice, $estimate->taxes->toArray());
        }

        $this->copyCustomFields($estimate, $invoice);

        $estimate->checkForEstimateConvertAction();

        return Invoice::find($invoice->id);
    }

    public function changeStatus(Estimate $estimate, string $status): void
    {
        $estimate->update(['status' => $status]);
    }
}
