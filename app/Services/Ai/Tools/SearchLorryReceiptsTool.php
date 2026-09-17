<?php

namespace App\Services\Ai\Tools;

use App\Models\LorryReceipt;

/**
 * Search Lorry Receipts by free text, scoped to the current company.
 *
 * Returns a compact list with challan numbers, party names, routes, amounts, and settlement status.
 */
class SearchLorryReceiptsTool extends AiTool
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function name(): string
    {
        return 'search_lorry_receipts';
    }

    public function description(): string
    {
        return 'Search Lorry Receipts (vehicle hire receipts with Section C/D/E payment details) for the current company. Filter by free-text query (matches challan number, lorry number, contract number, owner/driver name) or settlement status. Returns a compact list with challan numbers, party names, routes, amounts, and settlement status.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional free-text search against challan number, lorry number, contract number, owner name, or driver name.',
                ],
                'is_settled' => [
                    'type' => 'boolean',
                    'description' => 'Optional filter: true for fully settled receipts (net_amount_payable is filled), false for pending.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'description' => 'Max rows to return (default 10, max 50).',
                ],
            ],
            'required' => [],
        ];
    }

    public function requiredAbility(): ?array
    {
        return ['view-invoice', LorryReceipt::class];
    }

    public function execute(array $arguments, int $companyId, int $userId): mixed
    {
        $limit = min((int) ($arguments['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT);

        $query = LorryReceipt::query()
            ->where('company_id', $companyId)
            ->latest('created_at')
            ->limit($limit);

        if (! empty($arguments['query'])) {
            $q = $arguments['query'];
            $query->where(function ($qb) use ($q) {
                $qb->where('challan_no', 'like', "%{$q}%")
                    ->orWhere('contract_no', 'like', "%{$q}%")
                    ->orWhere('lorry_no', 'like', "%{$q}%")
                    ->orWhere('owner_name', 'like', "%{$q}%")
                    ->orWhere('driver_name', 'like', "%{$q}%")
                    ->orWhere('from_name', 'like', "%{$q}%")
                    ->orWhere('to_name', 'like', "%{$q}%");
            });
        }

        if (isset($arguments['is_settled']) && $arguments['is_settled'] === true) {
            $query->whereNotNull('net_amount_payable')
                ->where('net_amount_payable', '!=', '');
        } elseif (isset($arguments['is_settled']) && $arguments['is_settled'] === false) {
            $query->where(function ($qb) {
                $qb->whereNull('net_amount_payable')
                    ->orWhere('net_amount_payable', '');
            });
        }

        return [
            'lorry_receipts' => $query->get()->map(fn (LorryReceipt $lr): array => [
                'id' => $lr->id,
                'challan_no' => $lr->challan_no,
                'contract_no' => $lr->contract_no,
                'lorry_no' => $lr->lorry_no,
                'from' => $lr->from_name,
                'to' => $lr->to_name,
                'paid_to' => $lr->paid_to,
                'customer_display_name' => $lr->customerDisplayName,
                'advance_amount' => $lr->advance_amount,
                'net_amount_payable' => $lr->net_amount_payable,
                'display_amount_due' => $lr->displayAmountDue,
                'total_from_invoices' => $lr->totalFromInvoices,
                'is_settled' => ! empty($lr->net_amount_payable),
                'date_created' => $this->asDate($lr->date_created ?? $lr->created_at),
            ])->all(),
        ];
    }
}
