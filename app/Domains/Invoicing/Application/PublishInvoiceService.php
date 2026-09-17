<?php

namespace App\Domains\Invoicing\Application;

use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Data\PublishInvoiceData;
use App\Domains\Invoicing\Events\InvoicePublished;
use Illuminate\Support\Facades\Event;

class PublishInvoiceService
{
    public function __construct(
        private InvoiceRepository $repository,
    ) {}

    public function execute(PublishInvoiceData $data)
    {
        $invoice = $this->repository->update($data->invoice_id, [
            'status' => 'published',
            'notes' => $data->notes,
        ]);

        Event::dispatch(new InvoicePublished($invoice));

        return $invoice;
    }
}
