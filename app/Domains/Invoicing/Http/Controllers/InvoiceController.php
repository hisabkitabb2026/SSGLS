<?php

namespace App\Domains\Invoicing\Http\Controllers;

use App\Domains\Invoicing\Application\CreateInvoiceService;
use App\Domains\Invoicing\Application\DuplicateInvoiceService;
use App\Domains\Invoicing\Application\PublishInvoiceService;
use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Data\CreateInvoiceData;
use App\Domains\Invoicing\Data\PublishInvoiceData;
use App\Domains\Invoicing\Http\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Http\Requests\UpdateInvoiceRequest;
use App\Domains\Invoicing\Http\Resources\InvoiceResource;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Services\Cache\DashboardCacheService;

class InvoiceController extends Controller
{
    public function __construct(
        private CreateInvoiceService $createService,
        private PublishInvoiceService $publishService,
        private DuplicateInvoiceService $duplicateService,
        private InvoiceRepository $repository,
        private DashboardCacheService $cacheService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = $this->repository->all(auth()->user()->company);

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $data = new CreateInvoiceData(
            customer_id: $request->customer_id,
            invoice_number: $request->invoice_number,
            status: 'draft',
            notes: $request->notes,
            discount_amount: $request->discount_amount,
            tax_amount: $request->tax_amount,
            total_amount: $request->total_amount,
            company_id: auth()->user()->company_id,
        );

        $invoice = $this->createService->execute($data);

        // Invalidate dashboard cache after invoice creation
        $this->cacheService->invalidate(auth()->user()->company_id);

        return new InvoiceResource($invoice);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $data = $request->validated();

        // Set base amounts when updating
        if (isset($data['total_amount'])) {
            $data['base_total'] = $data['total_amount'];
            $data['base_due_amount'] = $data['total_amount'];
        }
        if (isset($data['tax_amount'])) {
            $data['base_tax'] = $data['tax_amount'];
        }
        if (isset($data['discount_amount'])) {
            $data['base_discount_val'] = $data['discount_amount'];
        }

        $updated = $this->repository->update($invoice->id, $data);

        // Invalidate dashboard cache after invoice update
        $this->cacheService->invalidate($invoice->company_id);

        return new InvoiceResource($updated);
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);
        $companyId = $invoice->company_id;
        $this->repository->delete($invoice->id);

        // Invalidate dashboard cache after invoice deletion
        $this->cacheService->invalidate($companyId);

        return response()->noContent();
    }

    public function publish(Invoice $invoice)
    {
        $this->authorize('publish', $invoice);

        $published = $this->publishService->execute(
            new PublishInvoiceData(
                invoice_id: $invoice->id,
                notes: null,
            )
        );

        // Invalidate dashboard cache after invoice publication
        $this->cacheService->invalidate($invoice->company_id);

        return new InvoiceResource($published);
    }

    public function duplicate(Invoice $invoice)
    {
        $this->authorize('duplicate', $invoice);

        $duplicated = $this->duplicateService->execute(
            $invoice->id,
            auth()->user()->company_id
        );

        return new InvoiceResource($duplicated);
    }
}
