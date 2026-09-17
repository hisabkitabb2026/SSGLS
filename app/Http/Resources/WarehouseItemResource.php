<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateReceived = $this->date_received;
        if (is_string($dateReceived)) {
            $dateReceived = $dateReceived;
        } elseif ($dateReceived) {
            $dateReceived = $dateReceived->format('Y-m-d');
        }

        // Get consignee name from LR Receipt
        $consigneeName = null;
        if ($this->lr && $this->lr->consignee_customer_id) {
            $consignee = Customer::find($this->lr->consignee_customer_id);
            $consigneeName = $consignee?->name;
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'lr_id' => $this->lr_id,
            'warehouse_location' => $this->warehouse_location,
            'section_name' => $this->section_name,
            'date_received' => $dateReceived,
            'destination_city' => $this->destination_city,
            'status' => $this->status,
            'load_type' => $this->load_type,
            'promised_dispatch_date' => $this->promised_dispatch_date?->format('Y-m-d'),
            'weight_kg' => (float) $this->weight_kg,
            'no_of_packages' => $this->no_of_packages,
            'goods_description' => $this->goods_description,
            'consignor_name' => $this->consignor_name,
            'consignee_name' => $consigneeName ?? $this->consignee_name,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'days_in_warehouse' => $this->days_in_warehouse,
            'is_overdue' => $this->is_overdue,
            'days_until_deadline' => $this->days_until_deadline,
            'aging_bucket' => $this->aging_bucket,
            'consolidation_id' => $this->consolidation_id,
            'delivery_id' => $this->delivery_id,
            'split_from_warehouse_item_id' => $this->split_from_warehouse_item_id,
            'is_split_load' => $this->split_from_warehouse_item_id !== null,
            'consolidated_at' => $this->consolidation_id ? 'Yes' : 'No',
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'lr' => $this->lr ? new InvoiceResource($this->lr) : null,
            'company' => $this->company ? new CompanyResource($this->company) : null,
            'consolidation' => new ConsolidationGroupResource($this->whenLoaded('consolidation')),
            'load_trip' => new LoadTripResource($this->whenLoaded('loadTrip')),
        ];
    }
}
