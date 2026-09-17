<?php

namespace App\Domains\Transport\Contracts;

use App\Domains\Transport\Models\LorryPartyProfile;

interface PartyProfileRepository
{
    public function create(array $data): LorryPartyProfile;

    public function find(int $id): ?LorryPartyProfile;

    public function update(int $id, array $data): LorryPartyProfile;

    public function delete(int $id): bool;

    public function all($company);

    public function getByType(string $type, $company);
}
