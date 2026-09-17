<?php

namespace App\Http\Resources;

use App\Services\Document\TruckServiceScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckServiceScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'truck_id' => $this->truck_id, 'name' => $this->name, 'interval_km' => $this->interval_km, 'interval_days' => $this->interval_days, 'last_service_odometer_km' => $this->last_service_odometer_km, 'last_service_date' => $this->last_service_date?->toDateString(), 'next_due_odometer_km' => $this->next_due_odometer_km, 'next_due_date' => $this->next_due_date?->toDateString(), 'status' => app(TruckServiceScheduleService::class)->getStatus($this->loadMissing('truck')), 'notes' => $this->notes];
    }
}
