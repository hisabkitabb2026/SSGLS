<?php

namespace App\Domains\Customer\Http\Controllers;

use App\Domains\Customer\Application\CreateAddressService;
use App\Domains\Customer\Contracts\AddressRepository;
use App\Domains\Customer\Data\CreateAddressData;
use App\Domains\Customer\Http\Requests\StoreAddressRequest;
use App\Domains\Customer\Http\Requests\UpdateAddressRequest;
use App\Domains\Customer\Http\Resources\AddressResource;
use App\Domains\Customer\Models\Address;
use App\Http\Controllers\Controller;

class AddressController extends Controller
{
    public function __construct(
        private AddressRepository $repository,
        private CreateAddressService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Address::class);
        $addresses = Address::all();

        return AddressResource::collection($addresses);
    }

    public function store(StoreAddressRequest $request)
    {
        $this->authorize('create', Address::class);
        $address = $this->service->execute(
            new CreateAddressData(
                street: $request->validated('street'),
                city: $request->validated('city'),
                state: $request->validated('state'),
                postal_code: $request->validated('postal_code'),
                country: $request->validated('country'),
                customer_id: $request->validated('customer_id'),
            )
        );

        return new AddressResource($address);
    }

    public function show(Address $address)
    {
        $this->authorize('view', $address);

        return new AddressResource($address);
    }

    public function update(UpdateAddressRequest $request, Address $address)
    {
        $this->authorize('update', $address);
        $updated = $this->repository->update($address->id, $request->validated());

        return new AddressResource($updated);
    }

    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);
        $this->repository->delete($address->id);

        return response()->noContent();
    }
}
