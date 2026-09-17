<?php

namespace App\Domains\Customer\Adapters;

use App\Domains\Customer\Contracts\AddressRepository;
use App\Domains\Customer\Models\Address;
use Illuminate\Database\Eloquent\Collection;

class EloquentAddressRepository implements AddressRepository
{
    public function create(array $data): Address
    {
        return Address::create($data);
    }

    public function update(int $id, array $data): Address
    {
        $address = Address::findOrFail($id);
        $address->update($data);

        return $address;
    }

    public function find(int $id): Address
    {
        return Address::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return Address::destroy($id) > 0;
    }

    public function all($customer): Collection
    {
        return Address::where('customer_id', $customer)->get();
    }
}
