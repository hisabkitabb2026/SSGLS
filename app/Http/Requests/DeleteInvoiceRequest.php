<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Rules\RelationNotExist;

/**
 * Delete multiple invoices request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic.
 * Invoices with payments cannot be deleted.
 */
class DeleteInvoiceRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'invoices';
    }

    protected function addCustomRules(): array
    {
        return [
            new RelationNotExist(Invoice::class, 'payments'),
        ];
    }
}
