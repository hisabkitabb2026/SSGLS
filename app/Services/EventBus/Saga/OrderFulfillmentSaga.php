<?php

namespace App\Services\EventBus\Saga;

use App\Services\EventBus\Events\Event;
use Illuminate\Support\Facades\Log;

/**
 * Order Fulfillment Saga
 *
 * Example saga that orchestrates the order fulfillment process:
 * 1. Invoice Creation
 * 2. Payment Reception
 * 3. Shipment Creation
 * 4. Delivery Confirmation
 *
 * Uses compensation to handle failures and maintain consistency
 */
class OrderFulfillmentSaga extends Saga
{
    public string $invoiceId;

    public string $paymentId;

    public string $shipmentId;

    public float $totalAmount = 0;

    /**
     * Define the saga flow
     */
    public static function define(): array
    {
        return [
            'name' => 'OrderFulfillment',
            'timeout' => 3600, // 1 hour
            'steps' => [
                [
                    'name' => 'CreateInvoice',
                    'triggerOn' => 'InvoiceCreatedEvent',
                    'action' => function (Event $event, self $saga) {
                        $saga->invoiceId = $event->aggregateId;
                        $saga->totalAmount = $event->amount;

                        Log::info('Saga: Invoice created', [
                            'saga_id' => $saga->id,
                            'invoice_id' => $saga->invoiceId,
                        ]);
                    },
                    'compensate' => function (array $context) {
                        // Compensation: Cancel invoice
                        Log::info('Saga compensation: Canceling invoice');
                    },
                ],
                [
                    'name' => 'ReceivePayment',
                    'triggerOn' => 'PaymentReceivedEvent',
                    'action' => function (Event $event, self $saga) {
                        if ($event->amount >= $saga->totalAmount) {
                            $saga->paymentId = $event->aggregateId;

                            Log::info('Saga: Payment received', [
                                'saga_id' => $saga->id,
                                'payment_id' => $saga->paymentId,
                                'amount' => $event->amount,
                            ]);
                        }
                    },
                    'compensate' => function (array $context) {
                        // Compensation: Refund payment
                        Log::info('Saga compensation: Refunding payment');
                    },
                ],
                [
                    'name' => 'CreateShipment',
                    'triggerOn' => 'ShipmentCreatedEvent',
                    'action' => function (Event $event, self $saga) {
                        $saga->shipmentId = $event->aggregateId;

                        Log::info('Saga: Shipment created', [
                            'saga_id' => $saga->id,
                            'shipment_id' => $saga->shipmentId,
                        ]);
                    },
                    'compensate' => function (array $context) {
                        // Compensation: Cancel shipment
                        Log::info('Saga compensation: Canceling shipment');
                    },
                    'endsSaga' => true,
                ],
            ],
        ];
    }

    /**
     * Check if saga prerequisites are met
     */
    public function validateStep(string $stepName, Event $event): bool
    {
        return match ($stepName) {
            'ReceivePayment' => ! empty($this->invoiceId),
            'CreateShipment' => ! empty($this->invoiceId) && ! empty($this->paymentId),
            default => true,
        };
    }

    /**
     * Get saga status
     */
    public function getStatus(): array
    {
        return [
            'saga_id' => $this->id,
            'state' => $this->state,
            'invoice_id' => $this->invoiceId ?? null,
            'payment_id' => $this->paymentId ?? null,
            'shipment_id' => $this->shipmentId ?? null,
            'total_amount' => $this->totalAmount,
            'steps_completed' => count($this->events),
        ];
    }
}
