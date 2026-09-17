<?php

namespace App\Services\EventBus\Events;

/**
 * Invoice Created Event
 *
 * Triggered when a new invoice is created
 */
class InvoiceCreatedEvent extends Event
{
    public function __construct(
        public string $invoiceNumber,
        public float $amount,
        public string $currencyCode,
        public string $customerId,
        string $aggregateId,
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        parent::__construct($aggregateId, 'Invoice', $userId, $companyId);
    }

    public function getName(): string
    {
        return 'invoice.created';
    }
}
