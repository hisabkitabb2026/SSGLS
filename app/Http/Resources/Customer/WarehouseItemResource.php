<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Warehouse Item Resource for the Customer Portal.
 *
 * Shows goods stored in the warehouse with status, aging, and load type.
 * Customer can track their goods from receipt to delivery.
 */
class WarehouseItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lr_id' => $this->lr_id,
            'lr_number' => $this->whenLoaded('lr', fn () => $this->lr?->invoice_number),
            'destination_city' => $this->destination_city,
            'warehouse_location' => $this->warehouse_location,
            'weight_kg' => $this->weight_kg,
            'load_type' => $this->load_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'date_received' => $this->date_received,
            'promised_dispatch_date' => $this->promised_dispatch_date,
            'days_in_warehouse' => $this->days_in_warehouse,
            'is_overdue' => $this->is_overdue,
            'aging_bucket' => $this->aging_bucket,
            'days_until_deadline' => $this->days_until_deadline,
            'consolidation_id' => $this->consolidation_id,
            'delivery_id' => $this->delivery_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
