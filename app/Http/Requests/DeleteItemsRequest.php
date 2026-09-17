<?php

namespace App\Http\Requests;

use App\Models\Item;
use App\Rules\RelationNotExist;

/**
 * Delete multiple items request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic.
 * Items have additional constraints (cannot delete if used in invoices/estimates).
 */
class DeleteItemsRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'items';
    }

    protected function addCustomRules(): array
    {
        return [
            new RelationNotExist(Item::class, 'invoiceItems'),
            new RelationNotExist(Item::class, 'estimateItems'),
            new RelationNotExist(Item::class, 'taxes'),
        ];
    }
}
