<?php

namespace App\Services\EventBus\Events;

/**
 * Payment Received Event
 *
 * Triggered when a payment is received
 */
class PaymentReceivedEvent extends Event
{
    public function __construct(
        public string $invoiceId,
        public float $amount,
        public string $currencyCode,
        public string $paymentMethod,
        public ?string $transactionId = null,
        public ?string $notes = null,
        string $aggregateId = '',
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        parent::__construct($aggregateId ?: $invoiceId, 'Payment', $userId, $companyId);
    }

    public function getName(): string
    {
        return 'payment.received';
    }
}
