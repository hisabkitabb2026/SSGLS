<?php

namespace App\Http\Controllers\Company\Truck;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTruckServiceScheduleRequest;
use App\Http\Requests\StoreTruckServiceScheduleRequest;
use App\Http\Resources\TruckServiceScheduleResource;
use App\Models\TruckServiceSchedule;
use App\Services\Document\TruckServiceScheduleService;
use Illuminate\Http\Request;

class TruckServiceScheduleController extends Controller
{
    public function __construct(private TruckServiceScheduleService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TruckServiceSchedule::class);

        return TruckServiceScheduleResource::collection($this->service->getCompanySchedules($request->header('company')));
    }

    public function store(StoreTruckServiceScheduleRequest $request): TruckServiceScheduleResource
    {
        $this->authorize('create', TruckServiceSchedule::class);

        return new TruckServiceScheduleResource($this->service->create($request->header('company'), $request->validated()));
    }

    public function complete(CompleteTruckServiceScheduleRequest $request, int $id): TruckServiceScheduleResource
    {
        $schedule = TruckServiceSchedule::query()->where('company_id', $request->header('company'))->findOrFail($id);
        $this->authorize('update', $schedule);

        return new TruckServiceScheduleResource($this->service->complete($schedule, $request->validated()));
    }
}
