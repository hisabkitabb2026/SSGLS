<?php

namespace App\Services\Document;

use App\Models\Invoice;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Collection;

class WarehouseItemService
{
    public function create(array $data): WarehouseItem
    {
        return WarehouseItem::create([
            'company_id' => $data['company_id'],
            'lr_id' => $data['lr_id'],
            'warehouse_location' => $data['warehouse_location'] ?? null,
            'section_name' => $data['section_name'] ?? null,
            'date_received' => $data['date_received'] ?? now(),
            'destination_city' => $this->normalizeCityName($data['destination_city'] ?? null),
            'load_type' => $data['load_type'] ?? WarehouseItem::LOAD_TYPE_PART,
            'promised_dispatch_date' => $data['promised_dispatch_date'] ?? null,
            'weight_kg' => $data['weight_kg'] ?? 0,
            'no_of_packages' => $data['no_of_packages'] ?? 0,
            'goods_description' => $data['goods_description'] ?? null,
            'consignor_name' => $data['consignor_name'] ?? null,
            'consignee_name' => $data['consignee_name'] ?? null,
            'priority' => $data['priority'] ?? WarehouseItem::PRIORITY_NORMAL,
            'status' => $data['status'] ?? WarehouseItem::STATUS_STORED,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(WarehouseItem $item, array $data): WarehouseItem
    {
        $item->update([
            'warehouse_location' => $data['warehouse_location'] ?? $item->warehouse_location,
            'section_name' => $data['section_name'] ?? $item->section_name,
            'date_received' => $data['date_received'] ?? $item->date_received,
            'destination_city' => isset($data['destination_city'])
                ? $this->normalizeCityName($data['destination_city'])
                : $item->destination_city,
            'load_type' => $data['load_type'] ?? $item->load_type,
            'promised_dispatch_date' => $data['promised_dispatch_date'] ?? $item->promised_dispatch_date,
            'weight_kg' => $data['weight_kg'] ?? $item->weight_kg,
            'no_of_packages' => $data['no_of_packages'] ?? $item->no_of_packages,
            'goods_description' => $data['goods_description'] ?? $item->goods_description,
            'consignor_name' => $data['consignor_name'] ?? $item->consignor_name,
            'consignee_name' => $data['consignee_name'] ?? $item->consignee_name,
            'priority' => $data['priority'] ?? $item->priority,
            'status' => $data['status'] ?? $item->status,
            'notes' => $data['notes'] ?? $item->notes,
        ]);

        return $item->fresh();
    }

    public function getByCompanyAndId(int $companyId, int $itemId): ?WarehouseItem
    {
        return WarehouseItem::forCompany($companyId)
            ->with(['lr.customer', 'company'])
            ->find($itemId);
    }

    public function getCompanyItems(int $companyId, array $filters = []): Collection
    {
        $query = WarehouseItem::forCompany($companyId)
            ->with(['lr.customer', 'company']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        if (! empty($filters['warehouse_location'])) {
            $query->byLocation($filters['warehouse_location']);
        }

        return $query->orderByDesc('date_received')->get();
    }

    public function getItemsByDestination(int $companyId, string $destination): Collection
    {
        return WarehouseItem::forCompany($companyId)
            ->byDestination($destination)
            ->readyForConsolidation()
            ->with(['lr.customer', 'company'])
            ->orderByDesc('date_received')
            ->get();
    }

    public function getItemsByLocation(int $companyId, string $location): Collection
    {
        return WarehouseItem::forCompany($companyId)
            ->byLocation($location)
            ->with(['lr.customer', 'company'])
            ->orderByDesc('date_received')
            ->get();
    }

    public function getItemsByCompanyInLr(int $companyId, int $supplierCompanyId): Collection
    {
        return WarehouseItem::forCompany($companyId)
            ->whereHas('lr', function ($query) use ($supplierCompanyId) {
                $query->where('customer_id', $supplierCompanyId);
            })
            ->with(['lr.customer', 'company'])
            ->orderByDesc('date_received')
            ->get();
    }

    public function getStoredItems(int $companyId): Collection
    {
        return WarehouseItem::forCompany($companyId)
            ->stored()
            ->with(['lr.customer', 'company'])
            ->orderByDesc('date_received')
            ->get();
    }

    public function getDestinations(int $companyId): array
    {
        return WarehouseItem::forCompany($companyId)
            ->stored()
            ->distinct()
            ->pluck('destination_city')
            ->filter()
            ->values()
            ->toArray();
    }

    public function getWarehouses(int $companyId): array
    {
        return WarehouseItem::forCompany($companyId)
            ->distinct()
            ->pluck('warehouse_location')
            ->filter()
            ->values()
            ->toArray();
    }

    public function getDestinationSummary(int $companyId, string $destination): array
    {
        $items = $this->getItemsByDestination($companyId, $destination);

        $totalBoxes = 0;
        $totalWeight = 0;
        $totalRevenue = 0;

        foreach ($items as $item) {
            $lr = $item->lr;
            $totalBoxes += (int) $lr->quantity ?? 0;
            $totalWeight += (int) $lr->transport_total_weight ?? 0;
            $totalRevenue += (int) $lr->total ?? 0;
        }

        return [
            'destination' => $destination,
            'total_items' => $items->count(),
            'total_boxes' => $totalBoxes,
            'total_weight' => $totalWeight,
            'total_revenue' => $totalRevenue,
            'items' => $items,
        ];
    }

    public function getLocationUtilization(int $companyId): array
    {
        $locations = $this->getWarehouses($companyId);
        $utilization = [];

        foreach ($locations as $location) {
            $items = $this->getItemsByLocation($companyId, $location);
            $totalWeight = 0;

            foreach ($items as $item) {
                $totalWeight += (int) $item->lr->transport_total_weight ?? 0;
            }

            $utilization[$location] = [
                'location' => $location,
                'total_items' => $items->count(),
                'total_weight' => $totalWeight,
            ];
        }

        return $utilization;
    }

    public function getDashboardStats(int $companyId): array
    {
        $allItems = $this->getCompanyItems($companyId);
        $storedItems = $this->getStoredItems($companyId);
        $overdueItems = $this->getOverdueItems($companyId);

        $totalWeightKg = 0;
        $totalRevenue = 0;

        foreach ($allItems as $item) {
            $totalWeightKg += (float) $item->weight_kg ?? 0;
            $totalRevenue += (int) ($item->lr->total ?? 0);
        }

        // Aging buckets
        $agingBuckets = ['0-3' => 0, '4-7' => 0, '8-15' => 0, '15+' => 0];
        foreach ($storedItems as $item) {
            $bucket = $item->aging_bucket;
            if (isset($agingBuckets[$bucket])) {
                $agingBuckets[$bucket]++;
            }
        }

        return [
            'total_items' => $allItems->count(),
            'stored_items' => $storedItems->count(),
            'overdue_items' => $overdueItems->count(),
            'total_weight_kg' => $totalWeightKg,
            'total_revenue' => $totalRevenue,
            'unique_destinations' => count($this->getDestinations($companyId)),
            'unique_locations' => count($this->getWarehouses($companyId)),
            'aging_buckets' => $agingBuckets,
        ];
    }

    /**
     * Get items past their promised dispatch date that are still in the warehouse.
     */
    public function getOverdueItems(int $companyId): Collection
    {
        return WarehouseItem::forCompany($companyId)
            ->overdue()
            ->with(['lr.customer', 'company'])
            ->orderBy('promised_dispatch_date')
            ->get();
    }

    /**
     * Get aging report: items grouped by how long they've been in the warehouse.
     */
    public function getAgingReport(int $companyId): array
    {
        $storedItems = $this->getStoredItems($companyId);

        $buckets = [
            '0-3' => [],
            '4-7' => [],
            '8-15' => [],
            '15+' => [],
        ];

        foreach ($storedItems as $item) {
            $bucket = $item->aging_bucket;
            if (isset($buckets[$bucket])) {
                $buckets[$bucket][] = $item;
            }
        }

        return [
            '0-3' => ['count' => count($buckets['0-3']), 'items' => $buckets['0-3']],
            '4-7' => ['count' => count($buckets['4-7']), 'items' => $buckets['4-7']],
            '8-15' => ['count' => count($buckets['8-15']), 'items' => $buckets['8-15']],
            '15+' => ['count' => count($buckets['15+']), 'items' => $buckets['15+']],
        ];
    }

    public function updateStatus(WarehouseItem $item, string $status): WarehouseItem
    {
        if (! in_array($status, WarehouseItem::getStatuses())) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $item->update(['status' => $status]);

        return $item->fresh();
    }

    public function delete(WarehouseItem $item): bool
    {
        return $item->delete();
    }

    /**
     * Normalize a city name to Title Case so that "mumbai", "MUMBAI",
     * and "Mumbai" all resolve to the same destination grouping.
     * This prevents duplicate destination entries in the warehouse dashboard.
     */
    private function normalizeCityName(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        return mb_convert_case(trim($city), MB_CASE_TITLE, 'UTF-8');
    }

    public function getLrOptions(int $companyId): Collection
    {
        return Invoice::where('company_id', $companyId)
            ->where('template_name', 'lr_receipt')
            ->where('status', '!=', Invoice::STATUS_DRAFT)
            ->whereNotIn('id', function ($query) {
                $query->select('lr_id')->from('warehouse_items');
            })
            ->select('id', 'invoice_number', 'customer_id')
            ->with('customer')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findLrByInvoiceNumber(int $companyId, string $invoiceNumber): ?Invoice
    {
        return Invoice::where('company_id', $companyId)
            ->where('template_name', 'lr_receipt')
            ->where('invoice_number', $invoiceNumber)
            ->with('customer')
            ->first();
    }
}
