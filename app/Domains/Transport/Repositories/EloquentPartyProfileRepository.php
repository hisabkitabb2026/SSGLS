<?php

namespace App\Domains\Transport\Repositories;

use App\Domains\Transport\Contracts\PartyProfileRepository;
use App\Domains\Transport\Models\LorryPartyProfile;

class EloquentPartyProfileRepository implements PartyProfileRepository
{
    public function create(array $data): LorryPartyProfile
    {
        return LorryPartyProfile::create($data);
    }

    public function find(int $id): ?LorryPartyProfile
    {
        return LorryPartyProfile::find($id);
    }

    public function update(int $id, array $data): LorryPartyProfile
    {
        $profile = LorryPartyProfile::findOrFail($id);
        $profile->update($data);

        return $profile;
    }

    public function delete(int $id): bool
    {
        return (bool) LorryPartyProfile::destroy($id);
    }

    public function all($company)
    {
        return LorryPartyProfile::where('company_id', $company->id)->get();
    }

    public function getByType(string $type, $company)
    {
        return LorryPartyProfile::where('company_id', $company->id)
            ->where('type', $type)->get();
    }
}
