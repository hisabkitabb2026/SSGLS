<?php

namespace App\Http\Controllers\Company\Consolidation;

use App\Http\Controllers\Controller;
use App\Http\Requests\SplitWarehouseItemRequest;
use App\Http\Requests\StoreConsolidationGroupRequest;
use App\Http\Requests\UpdateConsolidationGroupRequest;
use App\Http\Resources\ConsolidationGroupResource;
use App\Models\ConsolidationGroup;
use App\Services\Document\ConsolidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsolidationGroupController extends Controller
{
    public function __construct(
        private ConsolidationService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ConsolidationGroup::class);

        $filters = [
            'status' => $request->query('status'),
            'destination' => $request->query('destination'),
        ];

        $groups = $this->service->getCompanyGroups($request->header('company'), $filters);

        return ConsolidationGroupResource::collection($groups);
    }

    public function show(Request $request, int $id): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('view', $group);

        return new ConsolidationGroupResource($group);
    }

    public function store(StoreConsolidationGroupRequest $request): ConsolidationGroupResource
    {
        $this->authorize('create', ConsolidationGroup::class);

        $group = $this->service->createGroup(
            $request->header('company'),
            $request->validated()
        );

        return new ConsolidationGroupResource($group);
    }

    public function update(UpdateConsolidationGroupRequest $request, int $id): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('update', $group);

        $updated = $this->service->updateGroup($group, $request->validated());

        return new ConsolidationGroupResource($updated);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('delete', $group);

        try {
            $this->service->deleteGroup($group);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json(['message' => 'Consolidation group deleted']);
    }

    /**
     * Add a warehouse item to a consolidation group.
     */
    public function addItem(Request $request, int $id): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('update', $group);

        $validated = $request->validate([
            'item_id' => 'required|integer',
        ]);

        $updated = $this->service->addItemToGroup(
            $request->header('company'),
            $id,
            $validated['item_id']
        );

        return new ConsolidationGroupResource($updated);
    }

    /**
     * Remove a warehouse item from a consolidation group.
     */
    public function removeItem(Request $request, int $id, int $itemId): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('update', $group);

        $updated = $this->service->removeItemFromGroup(
            $request->header('company'),
            $id,
            $itemId
        );

        return new ConsolidationGroupResource($updated);
    }

    /**
     * Split an LR load and add the selected portion to a consolidation group.
     */
    public function splitItem(SplitWarehouseItemRequest $request, int $id, int $itemId): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('update', $group);

        $updated = $this->service->splitItemToGroup(
            $request->header('company'),
            $id,
            $itemId,
            (float) $request->validated('weight_kg'),
            $request->validated('no_of_packages')
        );

        return new ConsolidationGroupResource($updated);
    }

    /**
     * Mark a consolidation group as ready for dispatch.
     */
    public function markReady(Request $request, int $id): ConsolidationGroupResource
    {
        $group = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $group, 404);

        $this->authorize('update', $group);

        $updated = $this->service->markReady($group);

        return new ConsolidationGroupResource($updated);
    }

    /**
     * Get consolidation candidates: stored, unassigned items grouped by destination.
     * This powers the consolidation board.
     */
    public function candidates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConsolidationGroup::class);

        $destination = $request->query('destination');
        $candidates = $this->service->getConsolidationCandidates(
            $request->header('company'),
            $destination
        );

        return response()->json([
            'data' => $candidates,
        ]);
    }
}
