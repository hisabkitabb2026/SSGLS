<?php

namespace App\Domains\Invoicing\Application;

use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Events\InvoiceDuplicated;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class DuplicateInvoiceService
{
    public function __construct(
        private InvoiceRepository $repository,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $newInvoice = $this->repository->create([
                'invoice_number' => $this->generateNewInvoiceNumber($invoice),
                'customer_id' => $invoice->customer_id,
                'company_id' => $invoice->company_id,
                'status' => 'DRAFT',
                'date' => now(),
                'due_date' => now()->addDays(30),
                'notes' => $invoice->notes,
                'tax_id' => $invoice->tax_id,
            ]);

            // CRITICAL: Copy all invoice items
            foreach ($invoice->items as $item) {
                $newInvoice->items()->create([
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_id' => $item->tax_id,
                ]);
            }

            Event::dispatch(new InvoiceDuplicated($newInvoice, $invoice));

            return $newInvoice;
        });
    }

    private function generateNewInvoiceNumber(Invoice $invoice): string
    {
        return $invoice->invoice_number.'-COPY-'.now()->format('YmdHis');
    }
}
