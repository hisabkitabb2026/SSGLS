<?php

namespace App\Domains\Invoicing\Data;

readonly class CreateInvoiceData
{
    public function __construct(
        public int $customer_id,
        public string $invoice_number,
        public string $status,
        public ?string $notes,
        public ?float $discount_amount,
        public ?float $tax_amount,
        public ?float $total_amount,
        public int $company_id,
    ) {}
}
