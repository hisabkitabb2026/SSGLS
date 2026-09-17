<?php

namespace App\Http\Controllers\Company\LoadTrip;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoadTripRequest;
use App\Http\Requests\UpdateLoadTripRequest;
use App\Http\Resources\LoadTripResource;
use App\Models\LoadTrip;
use App\Services\Document\LoadTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoadTripController extends Controller
{
    public function __construct(
        private LoadTripService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LoadTrip::class);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:planned,dispatched,delivered,cancelled',
            'destination' => 'nullable|string|max:100',
            'truck_id' => 'nullable|integer',
            'lr' => 'nullable|string|max:100',
            'driver' => 'nullable|string|max:100',
            'truck_number' => 'nullable|string|max:100',
            'route' => 'nullable|string|max:100',
            'dispatch_date_from' => 'nullable|date',
            'dispatch_date_to' => 'nullable|date',
            'delivered_date_from' => 'nullable|date',
            'delivered_date_to' => 'nullable|date',
        ]);

        $filters = [
            'status' => $validated['status'] ?? null,
            'destination' => $validated['destination'] ?? null,
            'truck_id' => $validated['truck_id'] ?? null,
            'lr' => $validated['lr'] ?? null,
            'driver' => $validated['driver'] ?? null,
            'truck_number' => $validated['truck_number'] ?? null,
            'route' => $validated['route'] ?? null,
            'dispatch_date_from' => $validated['dispatch_date_from'] ?? null,
            'dispatch_date_to' => $validated['dispatch_date_to'] ?? null,
            'delivered_date_from' => $validated['delivered_date_from'] ?? null,
            'delivered_date_to' => $validated['delivered_date_to'] ?? null,
        ];

        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginated = $this->service->getCompanyTripsPaginated(
            $request->header('company'),
            $filters,
            $perPage
        );

        return response()->json([
            'data' => LoadTripResource::collection($paginated->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('view', $trip);

        return new LoadTripResource($trip);
    }

    public function store(StoreLoadTripRequest $request): LoadTripResource
    {
        $this->authorize('create', LoadTrip::class);

        $trip = $this->service->createTrip(
            $request->header('company'),
            $request->validated()
        );

        return new LoadTripResource($trip->load('consolidationGroup'));
    }

    public function update(UpdateLoadTripRequest $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->updateTrip($trip, $request->validated());

        return new LoadTripResource($updated);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('delete', $trip);

        try {
            $this->service->deleteTrip($trip);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Load trip deleted']);
    }

    /**
     * Dispatch a load trip: set status to dispatched and update all linked items.
     * Note: Method is named dispatchTrip (not dispatch) to avoid conflict with
     * the base Controller::dispatch($job) method used for queueing jobs.
     */
    public function dispatchTrip(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->dispatchTrip($trip);

        return new LoadTripResource($updated);
    }

    /**
     * Mark a load trip as delivered.
     */
    public function markDelivered(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->markDelivered($trip);

        return new LoadTripResource($updated);
    }

    /**
     * Cancel a load trip: set status to cancelled, release truck,
     * and revert warehouse items to stored.
     */
    public function cancelTrip(Request $request, int $id): LoadTripResource
    {
        $trip = $this->service->getByCompanyAndId($request->header('company'), $id);

        abort_if(! $trip, 404);

        $this->authorize('update', $trip);

        $updated = $this->service->cancelTrip($trip);

        return new LoadTripResource($updated);
    }
}
