<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Events\InvoicePaid;

class UpdateStatusOnPaymentRecorded
{
    public function handle(InvoicePaid $event): void
    {
        // Log event
        \Log::info('Invoice Paid', [
            'invoice_id' => $event->invoice->id,
            'status' => 'paid',
        ]);

        // Could send confirmation email here
    }
}
