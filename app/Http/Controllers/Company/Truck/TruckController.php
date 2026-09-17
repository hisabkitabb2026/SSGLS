<?php

namespace App\Http\Controllers\Company\Truck;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTruckRequest;
use App\Http\Requests\UpdateTruckRequest;
use App\Http\Resources\TruckResource;
use App\Models\Truck;
use App\Services\Document\TruckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(private TruckService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Truck::class);

        $filters = $request->only(['owner_id', 'status', 'min_capacity', 'max_capacity', 'search']);

        return TruckResource::collection($this->service->getCompanyTrucks($request->header('company'), $filters));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Truck::class);

        return response()->json(['data' => $this->service->getDashboard($request->header('company'))]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTruckRequest $request): TruckResource
    {
        $this->authorize('create', Truck::class);

        return new TruckResource($this->service->create($request->header('company'), $request->validated())->load('ownerProfile'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): TruckResource
    {
        $truck = Truck::forCompany($request->header('company'))
            ->with('ownerProfile')
            ->with(['loadTrips' => function ($query) {
                $query->with(['truck', 'driverProfile', 'warehouseItems.lr'])
                    ->orderByDesc('created_at');
            }])
            ->findOrFail($id);
        $this->authorize('view', $truck);

        return new TruckResource($truck);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTruckRequest $request, int $id): TruckResource
    {
        $truck = Truck::forCompany($request->header('company'))->findOrFail($id);
        $this->authorize('update', $truck);

        return new TruckResource($this->service->update($truck, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $truck = Truck::forCompany($request->header('company'))->findOrFail($id);
        $this->authorize('delete', $truck);
        $this->service->deleteTruck($truck);

        return response()->json(['message' => 'Truck deleted']);
    }

    // Compliance/document management removed — not needed for current phase.
    // public function showDocument(Request $request, int $id, string $document)
    // {
    //     $truck = Truck::forCompany($request->header('company'))->findOrFail($id);
    //     $this->authorize('view', $truck);
    //     abort_unless(in_array($document, ['rc_document', 'insurance_document', 'fitness_document', 'permit_document'], true), 404);
    //     $media = $truck->getFirstMedia($document);
    //     abort_unless($media, 404);
    //
    //     return response()->file($media->getPath());
    // }
}
