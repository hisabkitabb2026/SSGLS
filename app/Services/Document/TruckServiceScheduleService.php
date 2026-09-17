<?php

namespace App\Services\Document;

use App\Models\Truck;
use App\Models\TruckServiceSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TruckServiceScheduleService
{
    public function getCompanySchedules(int $companyId): Collection
    {
        return TruckServiceSchedule::query()->where('company_id', $companyId)->with('truck')->orderBy('next_due_date')->get();
    }

    public function create(int $companyId, array $data): TruckServiceSchedule
    {
        $truck = Truck::forCompany($companyId)->findOrFail($data['truck_id']);
        $lastOdometer = $data['last_service_odometer_km'] ?? $truck->current_odometer_km;
        $lastDate = $data['last_service_date'] ?? today()->toDateString();

        return TruckServiceSchedule::create([
            'company_id' => $companyId, 'truck_id' => $truck->id, 'name' => $data['name'], 'interval_km' => $data['interval_km'] ?? null, 'interval_days' => $data['interval_days'] ?? null,
            'last_service_odometer_km' => $lastOdometer, 'last_service_date' => $lastDate,
            'next_due_odometer_km' => isset($data['interval_km']) && $lastOdometer !== null ? $lastOdometer + $data['interval_km'] : null,
            'next_due_date' => isset($data['interval_days']) ? now()->parse($lastDate)->addDays($data['interval_days'])->toDateString() : null,
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ])->load('truck');
    }

    public function complete(TruckServiceSchedule $schedule, array $data): TruckServiceSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule = TruckServiceSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $truck = Truck::query()->lockForUpdate()->findOrFail($schedule->truck_id);
            $odometer = $data['service_odometer_km'] ?? $truck->current_odometer_km;

            if ($odometer !== null && $truck->current_odometer_km !== null && $odometer < $truck->current_odometer_km) {
                throw ValidationException::withMessages(['service_odometer_km' => ['Service odometer cannot be lower than the confirmed truck reading.']]);
            }

            $date = $data['service_date'] ?? today()->toDateString();
            $schedule->update([
                'last_service_odometer_km' => $odometer, 'last_service_date' => $date,
                'next_due_odometer_km' => $schedule->interval_km && $odometer !== null ? $odometer + $schedule->interval_km : null,
                'next_due_date' => $schedule->interval_days ? now()->parse($date)->addDays($schedule->interval_days)->toDateString() : null,
            ]);

            return $schedule->fresh('truck');
        });
    }

    public function getStatus(TruckServiceSchedule $schedule): string
    {
        if (! $schedule->is_active) {
            return 'inactive';
        }
        if (($schedule->next_due_odometer_km !== null && $schedule->truck->current_odometer_km >= $schedule->next_due_odometer_km) || ($schedule->next_due_date && $schedule->next_due_date->lessThanOrEqualTo(today()))) {
            return 'overdue';
        }
        if (($schedule->next_due_odometer_km !== null && $schedule->truck->current_odometer_km !== null && $schedule->next_due_odometer_km - $schedule->truck->current_odometer_km <= max(500, (int) ($schedule->interval_km * 0.1))) || ($schedule->next_due_date && $schedule->next_due_date->lessThanOrEqualTo(today()->addDays(30)))) {
            return 'due_soon';
        }

        return 'scheduled';
    }
}
