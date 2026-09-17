<?php

namespace App\Services\EventBus\Handlers;

use App\Services\EventBus\Events\Event;
use App\Services\EventBus\Events\ExpenseSubmittedEvent;
use Illuminate\Support\Facades\Log;

/**
 * Expense Approval Handler
 *
 * Handles expense submission:
 * - Route to appropriate approver
 * - Create approval workflow
 * - Notify approver
 */
class ExpenseApprovalHandler extends EventHandler
{
    /**
     * Process the event
     */
    public function process(Event $event): void
    {
        if (! $event instanceof ExpenseSubmittedEvent) {
            return;
        }

        // Create approval task
        $this->createApprovalTask($event);

        // Route to approver based on amount and category
        $this->routeToApprover($event);

        // Send notification
        $this->notifyApprover($event);

        // Update expense status
        $this->updateExpenseStatus($event);
    }

    /**
     * Subscribe to expense submitted events
     */
    public static function subscribe(): array
    {
        return [
            'expense.submitted',
            'expense.*',
        ];
    }

    protected function createApprovalTask(ExpenseSubmittedEvent $event): void
    {
        Log::info('Creating approval task', [
            'expense_id' => $event->aggregateId,
            'amount' => $event->amount,
            'event_id' => $event->id,
        ]);

        // Create approval workflow record
    }

    protected function routeToApprover(ExpenseSubmittedEvent $event): void
    {
        $approverId = $this->determineApprover($event->amount, $event->categoryId);

        Log::info('Routing expense to approver', [
            'expense_id' => $event->aggregateId,
            'approver_id' => $approverId,
            'event_id' => $event->id,
        ]);
    }

    protected function notifyApprover(ExpenseSubmittedEvent $event): void
    {
        Log::info('Notifying approver', [
            'expense_id' => $event->aggregateId,
            'event_id' => $event->id,
        ]);
    }

    protected function updateExpenseStatus(ExpenseSubmittedEvent $event): void
    {
        Log::info('Updating expense status', [
            'expense_id' => $event->aggregateId,
            'status' => 'pending_approval',
            'event_id' => $event->id,
        ]);
    }

    protected function determineApprover(float $amount, string $categoryId): string
    {
        // Logic to determine appropriate approver based on amount threshold and category
        return 'default_approver';
    }
}
