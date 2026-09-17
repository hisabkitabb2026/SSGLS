<?php

namespace App\Services\Document;

use App\Models\LoadTrip;
use App\Models\Truck;
use App\Models\TruckMaintenance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TruckMaintenanceService
{
    public function getCompanyMaintenances(int $companyId): Collection
    {
        return TruckMaintenance::query()
            ->where('company_id', $companyId)
            ->with('truck')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->get();
    }

    public function create(int $companyId, array $data): TruckMaintenance
    {
        return DB::transaction(function () use ($companyId, $data) {
            $truck = Truck::forCompany($companyId)->lockForUpdate()->findOrFail($data['truck_id']);
            $status = $data['status'] ?? 'scheduled';

            $this->ensureTruckCanEnterMaintenance($truck, $status);

            $maintenance = TruckMaintenance::create([
                'company_id' => $companyId,
                'truck_id' => $truck->id,
                'type' => $data['type'],
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'completed_date' => $data['completed_date'] ?? null,
                'status' => $status,
                'cost' => $data['cost'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($status === 'in_progress') {
                $truck->update(['status' => Truck::STATUS_MAINTENANCE]);
            }

            return $maintenance->load('truck');
        });
    }

    public function update(TruckMaintenance $maintenance, array $data): TruckMaintenance
    {
        return DB::transaction(function () use ($maintenance, $data) {
            $maintenance = TruckMaintenance::query()->lockForUpdate()->findOrFail($maintenance->id);
            $truck = Truck::query()->lockForUpdate()->findOrFail($maintenance->truck_id);
            $status = $data['status'] ?? $maintenance->status;

            $this->ensureTruckCanEnterMaintenance($truck, $status, $maintenance);

            $maintenance->update([
                'type' => $data['type'] ?? $maintenance->type,
                'scheduled_date' => $data['scheduled_date'] ?? $maintenance->scheduled_date,
                'completed_date' => $data['completed_date'] ?? $maintenance->completed_date,
                'status' => $status,
                'cost' => $data['cost'] ?? $maintenance->cost,
                'notes' => $data['notes'] ?? $maintenance->notes,
            ]);

            if ($status === 'in_progress') {
                $truck->update(['status' => Truck::STATUS_MAINTENANCE]);
            }

            if (in_array($status, ['completed', 'cancelled'], true)
                && $truck->status === Truck::STATUS_MAINTENANCE
                && ! $this->hasOtherInProgressMaintenance($truck, $maintenance->id)) {
                $truck->update(['status' => Truck::STATUS_AVAILABLE]);
            }

            return $maintenance->fresh('truck');
        });
    }

    public function delete(TruckMaintenance $maintenance): void
    {
        DB::transaction(function () use ($maintenance) {
            $maintenance = TruckMaintenance::query()->lockForUpdate()->findOrFail($maintenance->id);

            if ($maintenance->status === 'in_progress') {
                throw ValidationException::withMessages([
                    'maintenance' => ['An in-progress maintenance record must be completed or cancelled before it can be deleted.'],
                ]);
            }

            $maintenance->delete();
        });
    }

    private function ensureTruckCanEnterMaintenance(Truck $truck, string $status, ?TruckMaintenance $maintenance = null): void
    {
        if ($status !== 'in_progress') {
            return;
        }

        if ($truck->loadTrips()
            ->whereIn('status', [LoadTrip::STATUS_PLANNED, LoadTrip::STATUS_DISPATCHED])
            ->exists()) {
            throw ValidationException::withMessages([
                'truck_id' => ['The selected truck has an active load trip and cannot enter maintenance.'],
            ]);
        }

        if ($this->hasOtherInProgressMaintenance($truck, $maintenance?->id)) {
            throw ValidationException::withMessages([
                'status' => ['This truck already has maintenance in progress.'],
            ]);
        }
    }

    private function hasOtherInProgressMaintenance(Truck $truck, ?int $exceptId = null): bool
    {
        return $truck->maintenances()
            ->where('status', 'in_progress')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }
}
