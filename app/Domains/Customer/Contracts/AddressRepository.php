<?php

namespace App\Domains\Customer\Contracts;

use App\Domains\Customer\Models\Address;
use Illuminate\Database\Eloquent\Collection;

interface AddressRepository
{
    public function create(array $data): Address;

    public function update(int $id, array $data): Address;

    public function find(int $id): Address;

    public function delete(int $id): bool;

    public function all($customer): Collection;
}
