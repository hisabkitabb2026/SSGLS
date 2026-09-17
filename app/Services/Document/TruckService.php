<?php

namespace App\Services\Document;

use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TruckService
{
    /**
     * Get company trucks with optional filters.
     *
     * Supported filters:
     * - owner_id: filter by owner_profile_id
     * - status: filter by truck status
     * - min_capacity / max_capacity: capacity range in kg
     * - search: search by truck_number
     */
    public function getCompanyTrucks(int $companyId, array $filters = []): Collection
    {
        $query = Truck::forCompany($companyId)->with('ownerProfile')->orderBy('truck_number');

        if (! empty($filters['owner_id'])) {
            $query->where('owner_profile_id', $filters['owner_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['min_capacity'])) {
            $query->where('capacity_kg', '>=', (float) $filters['min_capacity']);
        }

        if (! empty($filters['max_capacity'])) {
            $query->where('capacity_kg', '<=', (float) $filters['max_capacity']);
        }

        if (! empty($filters['search'])) {
            $query->where('truck_number', 'like', '%'.$filters['search'].'%');
        }

        return $query->get();
    }

    public function getDashboard(int $companyId): array
    {
        $trucks = $this->getCompanyTrucks($companyId);

        return [
            'total_trucks' => $trucks->count(),
            'available_trucks' => $trucks->where('status', Truck::STATUS_AVAILABLE)->count(),
            'on_trip_trucks' => $trucks->where('status', Truck::STATUS_ON_TRIP)->count(),
            'maintenance_trucks' => $trucks->where('status', Truck::STATUS_MAINTENANCE)->count(),
            'driver_licence_attention_count' => LorryPartyProfile::query()
                ->where('company_id', $companyId)
                ->where('type', LorryPartyProfile::TYPE_DRIVER)
                ->whereNotNull('valid_up_to')
                ->get()
                ->filter(fn (LorryPartyProfile $driver) => Carbon::parse($driver->valid_up_to)->lessThanOrEqualTo(now()->addDays(30)))
                ->count(),
            'trucks' => $trucks,
        ];
    }

    public function create(int $companyId, array $data): Truck
    {
        return Truck::create([
            'company_id' => $companyId,
            'status' => Truck::STATUS_AVAILABLE,
            ...$data,
        ]);
    }

    public function update(Truck $truck, array $data): Truck
    {
        $truck->update($data);

        return $truck->fresh('ownerProfile');
    }

    /**
     * Delete a truck, guarding against active (planned/dispatched) trips.
     *
     * @throws ValidationException If the truck has active trips.
     */
    public function deleteTruck(Truck $truck): bool
    {
        $hasActiveTrips = $truck->loadTrips()
            ->whereIn('status', [LoadTrip::STATUS_PLANNED, LoadTrip::STATUS_DISPATCHED])
            ->exists();

        if ($hasActiveTrips) {
            throw ValidationException::withMessages([
                'truck' => ['Cannot delete a truck that has active (planned or dispatched) trips.'],
            ]);
        }

        return $truck->delete();
    }

    /**
     * Ensure a truck is available for a load of the given weight.
     *
     * Compliance/document checks removed — not needed for current phase.
     */
    public function ensureAvailableForLoad(Truck $truck, float $loadWeightKg): void
    {
        if (($truck->status ?? Truck::STATUS_AVAILABLE) !== Truck::STATUS_AVAILABLE) {
            throw ValidationException::withMessages(['truck_id' => ['The selected truck is not available.']]);
        }

        if ((float) $truck->capacity_kg < $loadWeightKg) {
            throw ValidationException::withMessages(['truck_id' => ['The selected truck does not have enough capacity for this load.']]);
        }
    }
}
