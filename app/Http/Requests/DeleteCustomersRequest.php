<?php

namespace App\Http\Requests;

/**
 * Delete multiple customers request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic
 * across all bulk-delete endpoints.
 */
class DeleteCustomersRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'customers';
    }
}
