<?php

namespace App\Http\Controllers\Company\Truck;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTruckOdometerReadingRequest;
use App\Http\Resources\TruckOdometerReadingResource;
use App\Models\TruckOdometerReading;
use App\Services\Document\TruckOdometerService;
use Illuminate\Http\Request;

class TruckOdometerReadingController extends Controller
{
    public function __construct(private TruckOdometerService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TruckOdometerReading::class);

        return TruckOdometerReadingResource::collection($this->service->getCompanyReadings($request->header('company')));
    }

    public function store(StoreTruckOdometerReadingRequest $request): TruckOdometerReadingResource
    {
        $this->authorize('create', TruckOdometerReading::class);

        return new TruckOdometerReadingResource($this->service->record($request->header('company'), $request->validated(), $request->file('reading_image')));
    }

    public function show(Request $request, int $id): TruckOdometerReadingResource
    {
        $reading = TruckOdometerReading::query()->where('company_id', $request->header('company'))->findOrFail($id);
        $this->authorize('view', $reading);

        return new TruckOdometerReadingResource($reading);
    }

    public function showImage(Request $request, int $id)
    {
        $reading = TruckOdometerReading::query()->where('company_id', $request->header('company'))->findOrFail($id);
        $this->authorize('view', $reading);
        $media = $reading->getFirstMedia('odometer_reading_image');

        abort_unless($media, 404);

        return response()->file($media->getPath());
    }
}
