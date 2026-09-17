<?php

namespace App\Domains\Invoicing\Application;

use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Data\CreateInvoiceData;
use App\Domains\Invoicing\Events\InvoiceCreated;
use Illuminate\Support\Facades\Event;

class CreateInvoiceService
{
    public function __construct(
        private InvoiceRepository $repository,
    ) {}

    public function execute(CreateInvoiceData $data)
    {
        $invoice = $this->repository->create([
            'customer_id' => $data->customer_id,
            'invoice_number' => $data->invoice_number,
            'status' => $data->status,
            'notes' => $data->notes,
            'discount_amount' => $data->discount_amount,
            'tax_amount' => $data->tax_amount,
            'total_amount' => $data->total_amount,
            'company_id' => $data->company_id,
            // Set base amounts (in company base currency)
            'base_total' => $data->total_amount,
            'base_tax' => $data->tax_amount,
            'base_discount_val' => $data->discount_amount,
            'base_due_amount' => $data->total_amount,
        ]);

        Event::dispatch(new InvoiceCreated($invoice));

        return $invoice;
    }
}
