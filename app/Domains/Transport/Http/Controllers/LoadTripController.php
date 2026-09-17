<?php

namespace App\Domains\Transport\Http\Controllers;

use App\Domains\Transport\Application\CreateLoadTripService;
use App\Domains\Transport\Data\CreateLoadTripData;
use App\Domains\Transport\Http\Requests\StoreLoadTripRequest;
use App\Domains\Transport\Http\Requests\UpdateLoadTripRequest;
use App\Domains\Transport\Http\Resources\LoadTripResource;
use App\Domains\Transport\Models\LoadTrip;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LoadTripController extends Controller
{
    public function __construct(
        private CreateLoadTripService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LoadTrip::class);
        $trips = LoadTrip::where('company_id', auth()->user()->company_id)->get();

        return LoadTripResource::collection($trips);
    }

    public function store(StoreLoadTripRequest $request)
    {
        $this->authorize('create', LoadTrip::class);

        $data = new CreateLoadTripData(
            name: $request->name,
            consolidation_id: $request->consolidation_id,
            status: $request->status ?? 'pending',
            notes: $request->notes,
            company_id: auth()->user()->company_id,
        );

        $trip = $this->service->execute($data);

        return new LoadTripResource($trip);
    }

    public function show(LoadTrip $trip)
    {
        $this->authorize('view', $trip);

        return new LoadTripResource($trip);
    }

    public function update(UpdateLoadTripRequest $request, LoadTrip $trip)
    {
        $this->authorize('update', $trip);

        $trip->update($request->validated());

        return new LoadTripResource($trip);
    }

    public function destroy(LoadTrip $trip)
    {
        $this->authorize('delete', $trip);
        $trip->delete();

        return response()->noContent();
    }
}
