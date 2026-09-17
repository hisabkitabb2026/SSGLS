<?php

namespace App\Http\Controllers\Company\Truck;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTruckMaintenanceRequest;
use App\Http\Requests\UpdateTruckMaintenanceRequest;
use App\Http\Resources\TruckMaintenanceResource;
use App\Models\TruckMaintenance;
use App\Services\Document\TruckMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckMaintenanceController extends Controller
{
    public function __construct(private TruckMaintenanceService $service) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', TruckMaintenance::class);

        return TruckMaintenanceResource::collection($this->service->getCompanyMaintenances($request->header('company')));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTruckMaintenanceRequest $request): TruckMaintenanceResource
    {
        $this->authorize('create', TruckMaintenance::class);

        return new TruckMaintenanceResource($this->service->create($request->header('company'), $request->validated()));
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): TruckMaintenanceResource
    {
        $maintenance = TruckMaintenance::query()
            ->where('company_id', $request->header('company'))
            ->with('truck')
            ->findOrFail($id);
        $this->authorize('view', $maintenance);

        return new TruckMaintenanceResource($maintenance);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTruckMaintenanceRequest $request, int $id): TruckMaintenanceResource
    {
        $maintenance = TruckMaintenance::query()->where('company_id', $request->header('company'))->findOrFail($id);
        $this->authorize('update', $maintenance);

        return new TruckMaintenanceResource($this->service->update($maintenance, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $maintenance = TruckMaintenance::query()->where('company_id', $request->header('company'))->findOrFail($id);
        $this->authorize('delete', $maintenance);
        $this->service->delete($maintenance);

        return response()->json(['message' => 'Maintenance record deleted']);
    }
}
