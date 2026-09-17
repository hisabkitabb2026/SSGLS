<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consignment Tracking Resource for the Customer Portal.
 *
 * Returns a unified timeline of a consignment's journey:
 * LR Receipt → Warehouse → Consolidation → Load Trip → Delivery
 */
class ConsignmentTrackingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'docket_number' => $this['docket_number'],
            'from_name' => $this['from_name'] ?? null,
            'to_name' => $this['to_name'] ?? null,
            'status' => $this['status'] ?? 'unknown',
            'lr_receipt' => $this['lr_receipt'] ?? null,
            'warehouse_item' => $this['warehouse_item'] ?? null,
            'consolidation_group' => $this['consolidation_group'] ?? null,
            'load_trip' => $this['load_trip'] ?? null,
            'timeline' => $this['timeline'] ?? [],
        ];
    }
}
