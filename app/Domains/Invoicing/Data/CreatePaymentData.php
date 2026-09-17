<?php

namespace App\Domains\Invoicing\Data;

readonly class CreatePaymentData
{
    public function __construct(
        public int $invoice_id,
        public float $amount,
        public string $payment_method,
        public ?string $reference,
        public ?string $notes,
        public int $company_id,
    ) {}
}
