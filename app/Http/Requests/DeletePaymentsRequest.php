<?php

namespace App\Http\Requests;

/**
 * Delete multiple payments request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic
 * across all bulk-delete endpoints.
 */
class DeletePaymentsRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'payments';
    }
}
