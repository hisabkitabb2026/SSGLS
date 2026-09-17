<?php

namespace App\Services\EventBus\Handlers;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Events\InvoiceCreatedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Invoice Created Event Handler
 *
 * Handles logic when an invoice is created:
 * - Update customer statistics
 * - Generate invoice PDF
 * - Send notification
 */
class InvoiceCreatedHandler extends EventHandler
{
    /**
     * Process the event
     */
    public function process(Event $event): void
    {
        if (! $event instanceof InvoiceCreatedEvent) {
            return;
        }

        // Update customer invoice count and balance
        $this->updateCustomerStatistics($event);

        // Generate invoice PDF
        $this->generatePDF($event);

        // Send notification to customer
        $this->sendNotification($event);

        // Update accounting records
        $this->updateAccountingRecords($event);
    }

    /**
     * Subscribe to invoice created events
     */
    public static function subscribe(): array
    {
        return [
            'invoice.created',
            'invoice.*',
            '#',
        ];
    }

    protected function updateCustomerStatistics(InvoiceCreatedEvent $event): void
    {
        // Increment invoice count for customer
        \DB::table('customers')
            ->where('id', $event->customerId)
            ->increment('invoice_count');

        Log::info('Customer statistics updated', [
            'customer_id' => $event->customerId,
            'event_id' => $event->id,
        ]);
    }

    protected function generatePDF(InvoiceCreatedEvent $event): void
    {
        Log::info('Generating invoice PDF', [
            'invoice_id' => $event->aggregateId,
            'event_id' => $event->id,
        ]);

        // Implementation would generate actual PDF
    }

    protected function sendNotification(InvoiceCreatedEvent $event): void
    {
        Log::info('Sending invoice notification', [
            'customer_id' => $event->customerId,
            'invoice_id' => $event->aggregateId,
            'event_id' => $event->id,
        ]);

        // Implementation would send email/SMS notification
    }

    protected function updateAccountingRecords(InvoiceCreatedEvent $event): void
    {
        Log::info('Updating accounting records', [
            'invoice_id' => $event->aggregateId,
            'amount' => $event->amount,
            'event_id' => $event->id,
        ]);

        // Implementation would update GL entries, AR aging, etc.
    }
}
