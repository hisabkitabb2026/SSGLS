<?php

namespace App\Services\Document;

use App\Models\ConsolidationGroup;
use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Load Trip Service (Truck Dispatch)
 *
 * Manages the truck dispatch workflow:
 * - Creating load trips from consolidation groups (or direct full-load dispatches)
 * - Dispatching trips (sets all linked items to in_transit)
 * - Marking trips as delivered (sets all linked items to delivered)
 *
 * Future scaling:
 * // TODO: Integrate real GPS tracking API for live truck location
 * // TODO: Integrate Fastag API for toll tracking
 * // TODO: Add E-Way Bill API integration for compliance
 * // TODO: Support multi-leg trips (trip_segments table)
 */
class LoadTripService
{
    /**
     * Generate the next sequential trip number for a company.
     * Format: TRIP-{YEAR}-{0001}
     */
    public function generateTripNumber(int $companyId): string
    {
        $year = now()->year;
        $prefix = "TRIP-{$year}-";

        $lastTrip = LoadTrip::forCompany($companyId)
            ->where('trip_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastTrip) {
            $parts = explode('-', $lastTrip->trip_number);
            $next = (int) end($parts) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new load trip, optionally linked to a consolidation group.
     */
    public function createTrip(int $companyId, array $data): LoadTrip
    {
        $truck = ! empty($data['truck_id'])
            ? Truck::forCompany($companyId)->findOrFail($data['truck_id'])
            : null;
        $driver = ! empty($data['driver_profile_id'])
            ? LorryPartyProfile::query()
                ->where('company_id', $companyId)
                ->where('type', LorryPartyProfile::TYPE_DRIVER)
                ->findOrFail($data['driver_profile_id'])
            : null;
        $broker = ! empty($data['broker_profile_id'])
            ? LorryPartyProfile::query()
                ->where('company_id', $companyId)
                ->where('type', LorryPartyProfile::TYPE_BROKER)
                ->findOrFail($data['broker_profile_id'])
            : null;
        $loadWeightKg = ! empty($data['consolidation_group_id'])
            ? (float) ConsolidationGroup::forCompany($companyId)->findOrFail($data['consolidation_group_id'])->total_weight_kg
            : 0;

        if ($driver && LoadTrip::forCompany($companyId)
            ->where('driver_profile_id', $driver->id)
            ->whereIn('status', [LoadTrip::STATUS_PLANNED, LoadTrip::STATUS_DISPATCHED])
            ->exists()) {
            throw ValidationException::withMessages([
                'driver_profile_id' => ['The selected driver is already assigned to an active trip.'],
            ]);
        }

        if ($truck) {
            app(TruckService::class)->ensureAvailableForLoad($truck, $loadWeightKg);
        }

        $trip = LoadTrip::create([
            'company_id' => $companyId,
            'consolidation_group_id' => $data['consolidation_group_id'] ?? null,
            'truck_id' => $truck?->id,
            'driver_profile_id' => $driver?->id,
            'trip_number' => $this->generateTripNumber($companyId),
            'truck_number' => $data['truck_number'] ?? $truck?->truck_number,
            'driver_name' => $data['driver_name'] ?? $driver?->name,
            'driver_phone' => $data['driver_phone'] ?? $driver?->phone,
            'broker_profile_id' => $broker?->id,
            'broker_name' => $data['broker_name'] ?? $broker?->name,
            'broker_phone' => $data['broker_phone'] ?? $broker?->phone,
            'origin_city' => $data['origin_city'] ?? null,

            'destination_city' => $data['destination_city'],
            'dispatch_date' => $data['dispatch_date'] ?? null,
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'status' => LoadTrip::STATUS_PLANNED,
            'notes' => $data['notes'] ?? null,
        ]);

        // If linked to a consolidation group, link all its items to this trip
        if (! empty($data['consolidation_group_id'])) {
            $group = ConsolidationGroup::find($data['consolidation_group_id']);
            if ($group) {
                $group->items()->update(['delivery_id' => $trip->id]);
            }
        }

        if ($truck) {
            $truck->update(['status' => Truck::STATUS_RESERVED]);
        }

        return $trip;
    }

    /**
     * Update a load trip.
     */
    public function updateTrip(LoadTrip $trip, array $data): LoadTrip
    {
        DB::transaction(function () use ($trip, $data) {
            $newStatus = $data['status'] ?? $trip->status;

            $trip->update([
                'truck_number' => $data['truck_number'] ?? $trip->truck_number,
                'driver_name' => $data['driver_name'] ?? $trip->driver_name,
                'driver_phone' => $data['driver_phone'] ?? $trip->driver_phone,
                'dispatch_date' => $data['dispatch_date'] ?? $trip->dispatch_date,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $trip->expected_delivery_date,
                'actual_delivery_date' => $data['actual_delivery_date'] ?? $trip->actual_delivery_date,
                'status' => $newStatus,
                'notes' => $data['notes'] ?? $trip->notes,
            ]);

            if ($newStatus === LoadTrip::STATUS_CANCELLED && $trip->truck) {
                $trip->truck->update(['status' => Truck::STATUS_AVAILABLE]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Get a load trip by company and ID.
     */
    public function getByCompanyAndId(int $companyId, int $tripId): ?LoadTrip
    {
        return LoadTrip::forCompany($companyId)
            ->with(['consolidationGroup.items.lr.customer', 'warehouseItems.lr.customer', 'truck.ownerProfile', 'driverProfile', 'brokerProfile'])

            ->find($tripId);
    }

    /**
     * Get all load trips for a company, optionally filtered.
     */
    public function getCompanyTrips(int $companyId, array $filters = []): Collection
    {
        $query = LoadTrip::forCompany($companyId)
            ->with(['consolidationGroup']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', $filters['truck_id']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Paginated load trips for the Dispatch tab — supports date-range filters.
     * Eager-loads warehouseItems.lr so the frontend can show LR details per trip.
     */
    public function getCompanyTripsPaginated(int $companyId, array $filters = [], int $perPage = 10)
    {
        $query = LoadTrip::forCompany($companyId)
            ->with(['consolidationGroup', 'warehouseItems.lr']);

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['destination'])) {
            $query->byDestination($filters['destination']);
        }

        if (! empty($filters['truck_id'])) {
            $query->where('truck_id', $filters['truck_id']);
        }

        // LR filter: match invoice_number or lr_number on linked warehouse items' LR
        if (! empty($filters['lr'])) {
            $query->whereHas('warehouseItems.lr', function ($q) use ($filters) {
                $q->where('invoice_number', 'like', '%'.$filters['lr'].'%')
                    ->orWhere('lr_number', 'like', '%'.$filters['lr'].'%');
            });
        }

        // Driver filter: match driver_name
        if (! empty($filters['driver'])) {
            $query->where('driver_name', 'like', '%'.$filters['driver'].'%');
        }

        // Truck number filter: match truck_number column or linked truck's truck_number
        if (! empty($filters['truck_number'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('truck_number', 'like', '%'.$filters['truck_number'].'%')
                    ->orWhereHas('truck', function ($sq) use ($filters) {
                        $sq->where('truck_number', 'like', '%'.$filters['truck_number'].'%');
                    });
            });
        }

        // Route filter: match origin_city or destination_city
        if (! empty($filters['route'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('origin_city', 'like', '%'.$filters['route'].'%')
                    ->orWhere('destination_city', 'like', '%'.$filters['route'].'%');
            });
        }

        if (! empty($filters['dispatch_date_from'])) {
            $query->whereDate('dispatch_date', '>=', $filters['dispatch_date_from']);
        }

        if (! empty($filters['dispatch_date_to'])) {
            $query->whereDate('dispatch_date', '<=', $filters['dispatch_date_to']);
        }

        if (! empty($filters['delivered_date_from'])) {
            $query->whereDate('actual_delivery_date', '>=', $filters['delivered_date_from']);
        }

        if (! empty($filters['delivered_date_to'])) {
            $query->whereDate('actual_delivery_date', '<=', $filters['delivered_date_to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Dispatch a load trip: set status to dispatched and update all linked items.
     */
    public function dispatchTrip(LoadTrip $trip): LoadTrip
    {
        DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => LoadTrip::STATUS_DISPATCHED,
                'dispatch_date' => $trip->dispatch_date ?? now(),
            ]);

            // Update all linked warehouse items to in_transit
            $trip->warehouseItems()->update([
                'status' => WarehouseItem::STATUS_IN_TRANSIT,
            ]);

            // Update consolidation group status if linked
            if ($trip->consolidationGroup) {
                $trip->consolidationGroup->update([
                    'status' => ConsolidationGroup::STATUS_DISPATCHED,
                ]);
            }

            if ($trip->truck) {
                $trip->truck->update(['status' => Truck::STATUS_ON_TRIP]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Mark a load trip as delivered: set status and update all linked items.
     */
    public function markDelivered(LoadTrip $trip): LoadTrip
    {
        DB::transaction(function () use ($trip) {
            $trip->update([
                'status' => LoadTrip::STATUS_DELIVERED,
                'actual_delivery_date' => $trip->actual_delivery_date ?? now()->toDateString(),
            ]);

            // Update all linked warehouse items to delivered
            $trip->warehouseItems()->update([
                'status' => WarehouseItem::STATUS_DELIVERED,
            ]);

            // Update consolidation group status if linked
            if ($trip->consolidationGroup) {
                $trip->consolidationGroup->update([
                    'status' => ConsolidationGroup::STATUS_COMPLETED,
                ]);
            }

            if ($trip->truck) {
                $trip->truck->update(['status' => Truck::STATUS_AVAILABLE]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Cancel a load trip: set status to cancelled, release truck, and
     * revert warehouse items to their pre-trip status.
     */
    public function cancelTrip(LoadTrip $trip): LoadTrip
    {
        if ($trip->status === LoadTrip::STATUS_DELIVERED) {
            throw new \DomainException('Cannot cancel a delivered trip.');
        }

        DB::transaction(function () use ($trip) {
            $trip->update(['status' => LoadTrip::STATUS_CANCELLED]);

            // Revert warehouse items to stored (if they were in_transit)
            $trip->warehouseItems()->update([
                'status' => WarehouseItem::STATUS_STORED,
            ]);

            // Revert consolidation group to open if it was dispatched
            if ($trip->consolidationGroup) {
                $trip->consolidationGroup->update([
                    'status' => ConsolidationGroup::STATUS_OPEN,
                ]);
            }

            // Release the truck back to available
            if ($trip->truck) {
                $trip->truck->update(['status' => Truck::STATUS_AVAILABLE]);
            }
        });

        return $trip->fresh();
    }

    /**
     * Delete a load trip.
     * Releases the truck and unlinks warehouse items before deletion.
     */
    public function deleteTrip(LoadTrip $trip): bool
    {
        DB::transaction(function () use ($trip) {
            // Unlink warehouse items
            $trip->warehouseItems()->update(['delivery_id' => null]);

            // Release the truck back to available
            if ($trip->truck) {
                $trip->truck->update(['status' => Truck::STATUS_AVAILABLE]);
            }

            // Revert consolidation group to open if it was dispatched
            if ($trip->consolidationGroup && $trip->consolidationGroup->status === ConsolidationGroup::STATUS_DISPATCHED) {
                $trip->consolidationGroup->update(['status' => ConsolidationGroup::STATUS_OPEN]);
            }

            $trip->delete();
        });

        return true;
    }
}
