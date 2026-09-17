<?php

namespace App\Http\Requests;

/**
 * Delete multiple expenses request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic
 * across all bulk-delete endpoints.
 */
class DeleteExpensesRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'expenses';
    }
}
