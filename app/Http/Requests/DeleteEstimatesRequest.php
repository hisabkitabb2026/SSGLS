<?php

namespace App\Http\Requests;

/**
 * Delete multiple estimates request.
 *
 * Extends BaseDeleteRequest to consolidate validation logic
 * across all bulk-delete endpoints.
 */
class DeleteEstimatesRequest extends BaseDeleteRequest
{
    protected function getTableName(): string
    {
        return 'estimates';
    }
}
