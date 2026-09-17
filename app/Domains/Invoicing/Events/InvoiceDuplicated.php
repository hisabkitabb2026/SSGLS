<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceDuplicated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invoice $duplicated,
        public Invoice $original,
    ) {}
}
