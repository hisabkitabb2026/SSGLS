<?php

namespace App\Services\Document;

use App\Models\Truck;
use App\Models\TruckOdometerReading;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TruckOdometerService
{
    public function getCompanyReadings(int $companyId): Collection
    {
        return TruckOdometerReading::query()->where('company_id', $companyId)->with('truck')->orderByDesc('recorded_at')->orderByDesc('id')->get();
    }

    public function record(int $companyId, array $data, UploadedFile $image): TruckOdometerReading
    {
        return DB::transaction(function () use ($companyId, $data, $image) {
            $truck = Truck::forCompany($companyId)->lockForUpdate()->findOrFail($data['truck_id']);
            $readingKm = (int) $data['reading_km'];

            if ($truck->current_odometer_km !== null && $readingKm < $truck->current_odometer_km) {
                throw ValidationException::withMessages(['reading_km' => ['The reading cannot be lower than the current confirmed odometer reading.']]);
            }

            $reading = TruckOdometerReading::create(['company_id' => $companyId, 'truck_id' => $truck->id, 'reading_km' => $readingKm, 'recorded_at' => $data['recorded_at'] ?? now(), 'notes' => $data['notes'] ?? null]);
            $reading->addMedia($image)->toMediaCollection('odometer_reading_image');
            $truck->update(['current_odometer_km' => $readingKm]);

            return $reading;
        });
    }
}
