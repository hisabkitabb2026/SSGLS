<?php

namespace App\Services\EventBus\Events;

/**
 * Customer Registered Event
 *
 * Triggered when a new customer is registered
 */
class CustomerRegisteredEvent extends Event
{
    public function __construct(
        public string $customerName,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $city,
        public ?string $country,
        string $aggregateId,
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        parent::__construct($aggregateId, 'Customer', $userId, $companyId);
    }

    public function getName(): string
    {
        return 'customer.registered';
    }
}
