<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
