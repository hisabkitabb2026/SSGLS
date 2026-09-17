<?php

namespace App\Domains\Product\Http\Controllers;

use App\Domains\Product\Application\CreateUnitService;
use App\Domains\Product\Contracts\UnitRepository;
use App\Domains\Product\Data\CreateUnitData;
use App\Domains\Product\Http\Requests\StoreUnitRequest;
use App\Domains\Product\Http\Resources\UnitResource;
use App\Domains\Product\Models\Unit;
use App\Http\Controllers\Controller;

class UnitController extends Controller
{
    public function __construct(
        private UnitRepository $repository,
        private CreateUnitService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Unit::class);
        $units = $this->repository->all();

        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request)
    {
        $this->authorize('create', Unit::class);
        $unit = $this->service->execute(
            new CreateUnitData(
                name: $request->validated('name'),
                symbol: $request->validated('symbol'),
                company_id: auth()->user()->company_id,
            )
        );

        return new UnitResource($unit);
    }

    public function show(Unit $unit)
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    public function update(StoreUnitRequest $request, Unit $unit)
    {
        $this->authorize('update', $unit);
        $updated = $this->repository->update($unit->id, $request->validated());

        return new UnitResource($updated);
    }

    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);
        $this->repository->delete($unit->id);

        return response()->noContent();
    }
}
