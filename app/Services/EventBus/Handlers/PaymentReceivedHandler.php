<?php

namespace App\Services\EventBus\Handlers;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Events\PaymentReceivedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Payment Received Handler
 *
 * Handles payment reception:
 * - Update invoice status to paid
 * - Update customer account balance
 * - Trigger accounting entries
 * - Generate receipt
 */
class PaymentReceivedHandler extends EventHandler
{
    /**
     * Process the event
     */
    public function process(Event $event): void
    {
        if (! $event instanceof PaymentReceivedEvent) {
            return;
        }

        // Update invoice payment status
        $this->markInvoiceAsPaid($event);

        // Update customer balance
        $this->updateCustomerBalance($event);

        // Create accounting entries
        $this->createAccountingEntries($event);

        // Generate receipt
        $this->generateReceipt($event);

        // Send confirmation
        $this->sendPaymentConfirmation($event);
    }

    /**
     * Subscribe to payment received events
     */
    public static function subscribe(): array
    {
        return [
            'payment.received',
            'payment.*',
        ];
    }

    protected function markInvoiceAsPaid(PaymentReceivedEvent $event): void
    {
        Log::info('Marking invoice as paid', [
            'invoice_id' => $event->invoiceId,
            'amount' => $event->amount,
            'event_id' => $event->id,
        ]);

        // Update invoice payment status
    }

    protected function updateCustomerBalance(PaymentReceivedEvent $event): void
    {
        Log::info('Updating customer balance', [
            'invoice_id' => $event->invoiceId,
            'amount' => $event->amount,
            'event_id' => $event->id,
        ]);

        // Reduce customer outstanding balance
    }

    protected function createAccountingEntries(PaymentReceivedEvent $event): void
    {
        Log::info('Creating accounting entries', [
            'invoice_id' => $event->invoiceId,
            'payment_method' => $event->paymentMethod,
            'event_id' => $event->id,
        ]);

        // GL entries:
        // Debit: Cash/Bank account
        // Credit: AR - Accounts Receivable
    }

    protected function generateReceipt(PaymentReceivedEvent $event): void
    {
        Log::info('Generating receipt', [
            'invoice_id' => $event->invoiceId,
            'transaction_id' => $event->transactionId,
            'event_id' => $event->id,
        ]);

        // Generate PDF receipt
    }

    protected function sendPaymentConfirmation(PaymentReceivedEvent $event): void
    {
        Log::info('Sending payment confirmation', [
            'invoice_id' => $event->invoiceId,
            'amount' => $event->amount,
            'event_id' => $event->id,
        ]);

        // Send email/SMS confirmation to customer
    }
}
