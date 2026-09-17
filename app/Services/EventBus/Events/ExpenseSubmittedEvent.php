<?php

namespace App\Services\EventBus\Events;

/**
 * Expense Submitted Event
 *
 * Triggered when an expense is submitted for approval
 */
class ExpenseSubmittedEvent extends Event
{
    public function __construct(
        public string $expenseNumber,
        public float $amount,
        public string $currencyCode,
        public string $categoryId,
        public string $vendorId,
        public ?\DateTime $expenseDate = null,
        string $aggregateId = '',
        ?string $userId = null,
        ?string $companyId = null,
    ) {
        parent::__construct($aggregateId ?: $expenseNumber, 'Expense', $userId, $companyId);
        $this->expenseDate ??= now();
    }

    public function getName(): string
    {
        return 'expense.submitted';
    }
}
