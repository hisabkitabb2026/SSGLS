<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Events\InvoiceCreated;
use App\Domains\Invoicing\Mail\InvoiceCreatedNotification;
use Illuminate\Support\Facades\Mail;

class NotifyOnInvoiceCreated
{
    public function handle(InvoiceCreated $event): void
    {
        // Log event
        \Log::info('Invoice Created', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
        ]);

        // Could send email here
        // Mail::send(new InvoiceCreatedNotification($event->invoice));
    }
}
