<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\LorryPartyProfileRequest;
use App\Http\Resources\LorryPartyProfileResource;
use App\Models\LorryPartyProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LorryPartyProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $query = LorryPartyProfile::whereCompany()
            ->applyFilters($request->all());

        if ($limit === 'all') {
            $profiles = $query->latest()->get();
        } else {
            $profiles = $query->latest()->paginate($limit);
        }

        return LorryPartyProfileResource::collection($profiles)
            ->response();
    }

    public function store(LorryPartyProfileRequest $request): JsonResponse
    {
        $this->authorize('create', LorryPartyProfile::class);

        $profile = LorryPartyProfile::create($request->getProfilePayload());

        return (new LorryPartyProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, LorryPartyProfile $lorryPartyProfile): JsonResponse
    {
        $this->authorize('view', $lorryPartyProfile);

        return (new LorryPartyProfileResource($lorryPartyProfile))
            ->response();
    }

    public function update(LorryPartyProfileRequest $request, LorryPartyProfile $lorryPartyProfile): JsonResponse
    {
        $this->authorize('update', $lorryPartyProfile);

        $lorryPartyProfile->update($request->getProfilePayload());

        return (new LorryPartyProfileResource($lorryPartyProfile))
            ->response();
    }

    public function destroy(Request $request, LorryPartyProfile $lorryPartyProfile): JsonResponse
    {
        $this->authorize('delete', $lorryPartyProfile);

        $lorryPartyProfile->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
