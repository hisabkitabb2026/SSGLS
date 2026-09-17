<?php

namespace App\Domains\Transport\Http\Controllers;

use App\Domains\Transport\Http\Requests\StorePartyProfileRequest;
use App\Domains\Transport\Http\Resources\LorryPartyProfileResource;
use App\Domains\Transport\Models\LorryPartyProfile;
use App\Http\Controllers\Controller;

class LorryPartyProfileController extends Controller
{
    public function index()
    {
        $profiles = LorryPartyProfile::where('company_id', auth()->user()->company_id)->paginate();

        return LorryPartyProfileResource::collection($profiles);
    }

    public function store(StorePartyProfileRequest $request)
    {
        $profile = LorryPartyProfile::create($request->validated());

        return new LorryPartyProfileResource($profile);
    }

    public function show(LorryPartyProfile $profile)
    {
        return new LorryPartyProfileResource($profile);
    }

    public function update(StorePartyProfileRequest $request, LorryPartyProfile $profile)
    {
        $profile->update($request->validated());

        return new LorryPartyProfileResource($profile);
    }

    public function destroy(LorryPartyProfile $profile)
    {
        $profile->delete();

        return response()->noContent();
    }
}
