<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Events\InvoicePublished;
use App\Domains\Invoicing\Mail\InvoicePublishedNotification;
use Illuminate\Support\Facades\Mail;

class SendEmailOnInvoicePublished
{
    public function handle(InvoicePublished $event): void
    {
        // Log event
        \Log::info('Invoice Published', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
        ]);

        // Send email to customer
        // Mail::send(new InvoicePublishedNotification($event->invoice));
    }
}
