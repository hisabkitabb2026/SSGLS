<?php

namespace App\Services\EventBus\Events;

/**
 * Shipment Created Event
 *
 * Triggered when a new shipment/transport is created
 */
class ShipmentCreatedEvent extends Event
{
    public function __construct(
        public string $trackingNumber,
        public string $origin,
        public string $destination,
        public float $weight,
        public string $weightUnit,
        public string $status,
        public ?string $estimatedDelivery = null,
        string $aggregateId = '',
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        parent::__construct($aggregateId ?: $trackingNumber, 'Shipment', $userId, $companyId);
    }

    public function getName(): string
    {
        return 'shipment.created';
    }
}
