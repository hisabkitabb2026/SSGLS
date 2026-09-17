<?php

namespace App\Services\Document;

use App\Models\ConsolidationGroup;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consolidation Service
 *
 * Manages the Part-Load consolidation workflow:
 * - Creating consolidation groups by destination
 * - Adding/removing warehouse items to/from groups
 * - Recalculating group aggregates (weight, packages, item count)
 * - Marking groups ready for dispatch
 *
 * Future scaling:
 * - AI-based consolidation recommendations (optimal grouping by weight/route)
 * - Auto-fill threshold configuration per route
 * - Multi-trip splitting for over-capacity groups
 */
class ConsolidationService
{
    /**
     * Generate the next sequential group number for a company.
     * Format: CONS-{YEAR}-{0001}
     */
    public function generateGroupNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "CONS-{$year}-";

        $lastGroup = ConsolidationGroup::forCompany($companyId)
            ->where('group_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastGroup) {
            $parts = explode('-', $lastGroup->group_number);
            $next = (int) end($parts) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new consolidation group for a destination.
     */
    public function createGroup(int $companyId, array $data): ConsolidationGroup
    {
        return ConsolidationGroup::create([
            'company_id' => $companyId,
            'group_number' => $this->generateGroupNumber($companyId),
            'destination_city' => $data['destination_city'],
            'truck_capacity_kg' => $data['truck_capacity_kg'] ?? 9000,
            'status' => ConsolidationGroup::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update a consolidation group.
     */
    public function updateGroup(ConsolidationGroup $group, array $data): ConsolidationGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group = ConsolidationGroup::query()
                ->lockForUpdate()
                ->findOrFail($group->id);
            $truckCapacityKg = $data['truck_capacity_kg'] ?? $group->truck_capacity_kg;

            if ((float) $truckCapacityKg < (float) $group->total_weight_kg) {
                throw ValidationException::withMessages([
                    'truck_capacity_kg' => [sprintf(
                        'Truck capacity cannot be less than the current load of %s kg.',
                        $this->formatWeight((float) $group->total_weight_kg)
                    )],
                ]);
            }

            $group->update([
                'truck_capacity_kg' => $truckCapacityKg,
                'notes' => $data['notes'] ?? $group->notes,
                'status' => $data['status'] ?? $group->status,
            ]);

            return $group->fresh();
        });
    }

    /**
     * Get a consolidation group by company and ID, with items loaded.
     */
    public function getByCompanyAndId(int $companyId, int $groupId): ?ConsolidationGroup
    {
        return ConsolidationGroup::forCompany($companyId)
            ->with(['items.lr.customer', 'loadTrips'])
            ->find($groupId);
    }

    /**
     * Get all consolidation groups for a company, optionally filtered.
     */
    public function getCompanyGroups(int $companyId, array $filters = []): Collection
    {
        $query = ConsolidationGroup::forCompany($companyId)
            ->with(['items.lr.customer']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Add a warehouse item to a consolidation group.
     * The item must be stored, unassigned, and destined for the same route.
     */
    public function addItemToGroup(int $companyId, int $groupId, int $itemId): ConsolidationGroup
    {
        DB::transaction(function () use ($companyId, $groupId, $itemId) {
            $group = ConsolidationGroup::forCompany($companyId)
                ->lockForUpdate()
                ->findOrFail($groupId);
            $item = WarehouseItem::forCompany($companyId)
                ->lockForUpdate()
                ->findOrFail($itemId);

            // Validate item is available for consolidation
            if ($item->consolidation_id !== null) {
                throw new \DomainException('Item is already assigned to a consolidation group.');
            }

            if ($item->status !== WarehouseItem::STATUS_STORED) {
                throw new \DomainException('Only stored items can be added to a consolidation group.');
            }

            if ($item->destination_city !== $group->destination_city) {
                throw new \DomainException('Item destination does not match the consolidation group destination.');
            }

            $remainingCapacityKg = (float) $group->truck_capacity_kg - (float) $group->total_weight_kg;
            if ((float) $item->weight_kg > $remainingCapacityKg) {
                throw ValidationException::withMessages([
                    'item_id' => [sprintf(
                        'Cannot add this LR: it weighs %s kg, but only %s kg capacity remains. Truck capacity is %s kg.',
                        $this->formatWeight((float) $item->weight_kg),
                        $this->formatWeight(max(0, $remainingCapacityKg)),
                        $this->formatWeight((float) $group->truck_capacity_kg)
                    )],
                ]);
            }

            $item->update([
                'consolidation_id' => $group->id,
                'status' => WarehouseItem::STATUS_PICKED,
            ]);

            $this->recalculateAggregates($group);
        });

        return ConsolidationGroup::forCompany($companyId)
            ->findOrFail($groupId)
            ->fresh('items.lr.customer');
    }

    private function formatWeight(float $weight): string
    {
        return number_format($weight, 2, '.', '');
    }

    /**
     * Assign part of an LR load to a group and leave the balance in the warehouse.
     */
    public function splitItemToGroup(
        int $companyId,
        int $groupId,
        int $itemId,
        float $weightKg,
        ?int $packages
    ): ConsolidationGroup {
        DB::transaction(function () use ($companyId, $groupId, $itemId, $weightKg, $packages) {
            $group = ConsolidationGroup::forCompany($companyId)
                ->lockForUpdate()
                ->findOrFail($groupId);
            $item = WarehouseItem::forCompany($companyId)
                ->lockForUpdate()
                ->findOrFail($itemId);

            $this->validateItemCanBeAdded($group, $item);

            if ($weightKg >= (float) $item->weight_kg) {
                throw ValidationException::withMessages([
                    'weight_kg' => ['The split weight must be less than the LR load weight. Use Add for the full LR load.'],
                ]);
            }

            $remainingCapacityKg = (float) $group->truck_capacity_kg - (float) $group->total_weight_kg;
            if ($weightKg > $remainingCapacityKg) {
                throw ValidationException::withMessages([
                    'weight_kg' => [sprintf(
                        'The selected %s kg exceeds the remaining truck capacity of %s kg.',
                        $this->formatWeight($weightKg),
                        $this->formatWeight(max(0, $remainingCapacityKg))
                    )],
                ]);
            }

            $this->validateSplitPackages($item, $packages);

            $remainingWeightKg = (float) $item->weight_kg - $weightKg;
            $selectedPackages = $item->no_of_packages > 0 ? $packages : 0;
            $remainingPackages = $item->no_of_packages > 0
                ? $item->no_of_packages - $selectedPackages
                : 0;

            $balanceItem = $item->replicate(['consolidation_id', 'delivery_id']);
            $balanceItem->weight_kg = $remainingWeightKg;
            $balanceItem->no_of_packages = $remainingPackages;
            $balanceItem->status = WarehouseItem::STATUS_STORED;
            $balanceItem->split_from_warehouse_item_id = $item->split_from_warehouse_item_id ?? $item->id;
            $balanceItem->save();

            $item->update([
                'weight_kg' => $weightKg,
                'no_of_packages' => $selectedPackages,
                'consolidation_id' => $group->id,
                'status' => WarehouseItem::STATUS_PICKED,
            ]);

            $this->recalculateAggregates($group);
        });

        return ConsolidationGroup::forCompany($companyId)
            ->findOrFail($groupId)
            ->fresh('items.lr.customer');
    }

    private function validateItemCanBeAdded(ConsolidationGroup $group, WarehouseItem $item): void
    {
        if ($item->consolidation_id !== null) {
            throw new \DomainException('Item is already assigned to a consolidation group.');
        }

        if ($item->status !== WarehouseItem::STATUS_STORED) {
            throw new \DomainException('Only stored items can be added to a consolidation group.');
        }

        if ($item->destination_city !== $group->destination_city) {
            throw new \DomainException('Item destination does not match the consolidation group destination.');
        }
    }

    private function validateSplitPackages(WarehouseItem $item, ?int $packages): void
    {
        if ($item->no_of_packages === 0) {
            return;
        }

        if ($packages === null || $packages < 1 || $packages >= $item->no_of_packages) {
            throw ValidationException::withMessages([
                'no_of_packages' => [sprintf(
                    'Select between 1 and %d whole packages for this partial load.',
                    $item->no_of_packages - 1
                )],
            ]);
        }
    }

    /**
     * Remove a warehouse item from a consolidation group.
     */
    public function removeItemFromGroup(int $companyId, int $groupId, int $itemId): ConsolidationGroup
    {
        $group = ConsolidationGroup::forCompany($companyId)->findOrFail($groupId);
        $item = WarehouseItem::forCompany($companyId)->findOrFail($itemId);

        if ($item->consolidation_id !== $group->id) {
            throw new \DomainException('Item does not belong to this consolidation group.');
        }

        DB::transaction(function () use ($item, $group) {
            $item->update([
                'consolidation_id' => null,
                'status' => WarehouseItem::STATUS_STORED,
            ]);

            $this->recalculateAggregates($group);
        });

        return $group->fresh('items.lr.customer');
    }

    /**
     * Mark a consolidation group as ready for dispatch.
     */
    public function markReady(ConsolidationGroup $group): ConsolidationGroup
    {
        if ($group->total_items === 0) {
            throw new \DomainException('Cannot mark an empty group as ready.');
        }

        $group->update(['status' => ConsolidationGroup::STATUS_READY]);

        return $group->fresh();
    }

    /**
     * Get consolidation candidates: stored, unassigned items grouped by destination.
     * Used by the consolidation board to show what's available to consolidate.
     */
    public function getConsolidationCandidates(int $companyId, ?string $destination = null): array
    {
        $query = WarehouseItem::forCompany($companyId)
            ->readyForConsolidation()
            ->with(['lr.customer']);

        if ($destination) {
            $query->byDestination($destination);
        }

        $items = $query->orderBy('date_received')->get();

        // Group by destination
        $grouped = [];
        foreach ($items as $item) {
            $dest = $item->destination_city ?: 'Unknown';
            if (! isset($grouped[$dest])) {
                $grouped[$dest] = [
                    'destination' => $dest,
                    'items' => [],
                    'total_weight_kg' => 0,
                    'total_packages' => 0,
                    'item_count' => 0,
                    'oldest_days' => 0,
                    'overdue_count' => 0,
                ];
            }

            $grouped[$dest]['items'][] = $item;
            $grouped[$dest]['total_weight_kg'] += (float) $item->weight_kg;
            $grouped[$dest]['total_packages'] += (int) $item->no_of_packages;
            $grouped[$dest]['item_count']++;

            $days = $item->days_in_warehouse ?? 0;
            if ($days > $grouped[$dest]['oldest_days']) {
                $grouped[$dest]['oldest_days'] = $days;
            }

            if ($item->is_overdue) {
                $grouped[$dest]['overdue_count']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Recalculate the aggregate fields on a consolidation group.
     */
    public function recalculateAggregates(ConsolidationGroup $group): void
    {
        $items = $group->items()->get();

        $group->update([
            'total_weight_kg' => $items->sum(fn ($i) => (float) $i->weight_kg),
            'total_packages' => $items->sum(fn ($i) => (int) $i->no_of_packages),
            'total_items' => $items->count(),
        ]);
    }

    /**
     * Delete a consolidation group.
     *
     * Completed groups can be safely deleted (items are already delivered).
     * Dispatched groups cannot be deleted because items are actively in transit.
     * Open/ready groups: items are unassigned back to stored status before deletion.
     */
    public function deleteGroup(ConsolidationGroup $group): bool
    {
        if ($group->status === ConsolidationGroup::STATUS_DISPATCHED) {
            throw new \DomainException('Cannot delete a dispatched consolidation group. Items are in transit.');
        }

        DB::transaction(function () use ($group) {
            // Unassign all items back to stored status (only for non-completed groups)
            if ($group->status !== ConsolidationGroup::STATUS_COMPLETED) {
                $group->items()->update([
                    'consolidation_id' => null,
                    'status' => WarehouseItem::STATUS_STORED,
                ]);
            }

            $group->delete();
        });

        return true;
    }
}
