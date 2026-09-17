<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteInvoiceRequest;
use App\Http\Requests\SendInvoiceRequest;
use App\Http\Resources\EstimateResource;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Document\InvoiceService;
use App\Services\Document\SerialNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class InvoicesController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Authorize based on the invoice template type.
     *
     * The InvoicesController handles both standard invoices (template_name like
     * 'invoice1', 'invoice2') and Invoice Receipts (template_name = 'office_invoice').
     * Standard invoices use 'view-invoice' / 'create-invoice' / 'edit-invoice'
     * abilities, while Invoice Receipts use 'view-invoice-receipt' /
     * 'create-invoice-receipt' / 'edit-invoice-receipt'.
     *
     * This method inspects the request's template_name and authorizes against
     * the correct ability set via custom gates defined in AppServiceProvider.
     */
    private function authorizeByTemplate(string $action, ?Invoice $invoice = null): void
    {
        $templateName = request()->input('template_name');

        // If this is an Invoice Receipt (office_invoice), use invoice-receipt abilities
        if ($templateName === 'office_invoice') {
            $ability = match ($action) {
                'view' => 'view invoice receipt',
                'create' => 'create invoice receipt',
                'edit' => 'edit invoice receipt',
                'delete' => 'delete invoice receipt',
                default => 'view invoice receipt',
            };

            if ($invoice) {
                $this->authorize($ability, $invoice);
            } else {
                $this->authorize($ability);
            }

            return;
        }

        // Fall back to standard invoice authorization via the InvoicePolicy
        $ability = match ($action) {
            'view' => 'viewAny',
            'create' => 'create',
            'edit' => 'update',
            'delete' => 'delete',
            default => 'viewAny',
        };

        if ($invoice) {
            $this->authorize($action === 'view' ? 'view' : $ability, $invoice);
        } else {
            $this->authorize($ability, Invoice::class);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        // Use template-based authorization: Invoice Receipts (office_invoice)
        // check 'view-invoice-receipt', standard invoices check 'view-invoice'.
        $this->authorizeByTemplate('view');

        $limit = $request->input('limit', 10);
        $templateName = $request->input('template_name');

        // Build base query - exclude transport templates only when no specific template is requested
        $baseQuery = Invoice::whereCompany();

        // Exclude all transport receipt templates (lr_receipt, lorry_receipt,
        // office_invoice) when no template_name is specified — this makes the
        // standard Invoices list show only standard invoice templates (invoice1,
        // invoice2, etc.). When a specific template_name IS provided (e.g.
        // office_invoice for Invoice Receipts, lorry_receipt, lr_receipt), only
        // that template's invoices are returned.
        if (! $request->filled('template_name')) {
            $baseQuery->whereNotIn('template_name', ['lr_receipt', 'lorry_receipt', 'office_invoice']);
        }

        // Eager load all necessary relations to prevent N+1 queries
        $invoices = $baseQuery
            ->applyFilters($request->all())
            ->with(['customer', 'consigneeCustomer', 'currency', 'items', 'taxes'])
            ->latest()
            ->paginateData($limit);

        // When limit=all, paginateData returns a Collection (not a Paginator),
        // so we can't call ->total() or ->additional() on it.
        if ($limit === 'all') {
            $totalCount = $invoices->count();

            return InvoiceResource::collection($invoices)
                ->additional(['meta' => [
                    'invoice_total_count' => $totalCount,
                ]]);
        }

        // Use cached count from paginator metadata instead of separate count() query
        // This is much faster as it uses the same query as the pagination
        $totalCount = $invoices->total();

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => [
                'invoice_total_count' => $totalCount,
            ]]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Requests\InvoicesRequest $request)
    {
        // Use template-based authorization: Invoice Receipts check
        // 'create-invoice-receipt', standard invoices check 'create-invoice'.
        $this->authorizeByTemplate('create');

        $invoice = $this->invoiceService->create($request);

        if ($request->has('invoiceSend')) {
            $this->invoiceService->send($invoice, $request->only(['subject', 'body']));
        }

        GenerateDocumentPdfJob::dispatch($invoice, 'invoice', 'invoice_number');

        return new InvoiceResource($invoice);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'consigneeCustomer', 'fields', 'items.fields.customField', 'currency', 'taxes', 'company', 'partyProfile']);

        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(Requests\InvoicesRequest $request, Invoice $invoice)
    {
        // Use template-based authorization: Invoice Receipts check
        // 'edit-invoice-receipt', standard invoices check 'edit-invoice'.
        $this->authorizeByTemplate('edit', $invoice);

        $invoice = $this->invoiceService->update($invoice, $request);

        GenerateDocumentPdfJob::dispatch($invoice, 'invoice', 'invoice_number', true);

        return new InvoiceResource($invoice);
    }

    /**
     * delete the specified resources in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteInvoiceRequest $request)
    {
        $this->authorize('delete multiple invoices');

        return $this->deleteBulk(Invoice::class, $request->ids, $this->invoiceService);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->send($invoice, $request->all());

        return $this->successResponse();
    }

    public function sendPreview(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->invoiceService->getSendData($invoice, $request->all());
        $data['url'] = $invoice->invoicePdfUrl;

        return $markdown->render('emails.send.invoice', ['data' => $data]);
    }

    public function clone(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $this->authorize('create', Invoice::class);

        $newInvoice = $this->invoiceService->clone($invoice);

        return new InvoiceResource($newInvoice);
    }

    public function convertToEstimate(Request $request, Invoice $invoice)
    {
        // Authorize access to the source invoice (tenant isolation) in addition
        // to the ability to create an estimate.
        $this->authorize('view', $invoice);
        $this->authorize('create', Estimate::class);

        $estimate = $this->invoiceService->convertToEstimate($invoice);

        return new EstimateResource($estimate);
    }

    public function changeStatus(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->changeStatus($invoice, $request->status);

        return $this->successResponse();
    }

    /**
     * Find an invoice by its invoice_number (e.g. LR Receipt Docket No).
     * Used by the Office Invoice form to auto-fill Consignment Details
     * when the user enters a Consignment No that matches an existing
     * LR Receipt's Docket No.
     *
     * @return JsonResponse
     */
    public function findByInvoiceNumber(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $request->validate([
            'invoice_number' => 'required|string',
            'template_name' => 'nullable|string',
        ]);

        $invoiceNumber = $request->input('invoice_number');
        $templateName = $request->input('template_name', 'lr_receipt');

        $invoice = Invoice::whereCompany()
            ->where('invoice_number', $invoiceNumber)
            ->where('template_name', $templateName)
            ->with(['customer', 'consigneeCustomer', 'fields', 'items.fields.customField', 'currency'])
            ->first();

        if (! $invoice) {
            return $this->notFoundResponse('Invoice not found');
        }

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Get the next invoice number based on company settings format.
     *
     * @return JsonResponse
     */
    public function getNextNumber(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $serial = (new SerialNumberService)
            ->setModel(Invoice::class)
            ->setCompany($request->header('company'))
            ->setCustomer($request->get('customer_id'))
            ->setTemplateName($request->get('template_name'))
            ->setNextNumbers();

        $nextNumber = $serial->getNextNumber();

        return response()->json([
            'success' => true,
            'data' => [
                'next_number' => $nextNumber,
            ],
        ]);
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->delete(collect([$invoice->id]));

        return $this->successResponse();
    }
}
