<?php

namespace App\Domains\Transport\Http\Controllers;

use App\Domains\Transport\Application\ConsolidateItemsService;
use App\Domains\Transport\Data\CreateConsolidationData;
use App\Domains\Transport\Http\Requests\StoreConsolidationRequest;
use App\Domains\Transport\Http\Requests\UpdateConsolidationRequest;
use App\Domains\Transport\Http\Resources\ConsolidationResource;
use App\Domains\Transport\Models\ConsolidationGroup;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsolidationController extends Controller
{
    public function __construct(
        private ConsolidateItemsService $service,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ConsolidationGroup::class);
        $consolidations = ConsolidationGroup::where('company_id', auth()->user()->company_id)->get();

        return ConsolidationResource::collection($consolidations);
    }

    public function store(StoreConsolidationRequest $request)
    {
        $this->authorize('create', ConsolidationGroup::class);

        $data = new CreateConsolidationData(
            name: $request->name,
            destination: $request->destination,
            status: $request->status ?? 'pending',
            company_id: auth()->user()->company_id,
            warehouse_items: $request->warehouse_items ?? [],
        );

        $consolidation = $this->service->execute($data);

        return new ConsolidationResource($consolidation);
    }

    public function show(ConsolidationGroup $consolidation)
    {
        $this->authorize('view', $consolidation);

        return new ConsolidationResource($consolidation);
    }

    public function update(UpdateConsolidationRequest $request, ConsolidationGroup $consolidation)
    {
        $this->authorize('update', $consolidation);

        $consolidation->update($request->validated());

        return new ConsolidationResource($consolidation);
    }

    public function destroy(ConsolidationGroup $consolidation)
    {
        $this->authorize('delete', $consolidation);
        $consolidation->delete();

        return response()->noContent();
    }
}
