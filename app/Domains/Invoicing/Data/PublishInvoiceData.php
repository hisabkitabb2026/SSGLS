<?php

namespace App\Domains\Invoicing\Data;

readonly class PublishInvoiceData
{
    public function __construct(
        public int $invoice_id,
        public ?string $notes,
    ) {}
}
