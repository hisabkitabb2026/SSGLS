<?php

namespace App\Domains\Customer\Application;

use App\Domains\Customer\Contracts\AddressRepository;
use App\Domains\Customer\Data\CreateAddressData;
use App\Domains\Customer\Events\AddressCreated;
use App\Domains\Customer\Models\Address;
use Illuminate\Support\Facades\Event;

class CreateAddressService
{
    public function __construct(
        private AddressRepository $repository,
    ) {}

    public function execute(CreateAddressData $data): Address
    {
        $address = $this->repository->create([
            'street' => $data->street,
            'city' => $data->city,
            'state' => $data->state,
            'postal_code' => $data->postal_code,
            'country' => $data->country,
            'customer_id' => $data->customer_id,
        ]);

        Event::dispatch(new AddressCreated($address));

        return $address;
    }
}
