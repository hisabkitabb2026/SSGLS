<?php

namespace App\Domains\Customer\Application;

use App\Domains\Customer\Contracts\CustomerRepository;
use App\Domains\Customer\Data\UpdateCustomerData;
use App\Domains\Customer\Events\CustomerUpdated;
use Illuminate\Support\Facades\Event;

class UpdateCustomerService
{
    public function __construct(
        private CustomerRepository $repository,
    ) {}

    public function execute(int $customerId, UpdateCustomerData $data)
    {
        $customer = $this->repository->update($customerId, [
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'customer_type' => $data->customer_type,
            'active' => $data->active,
        ]);

        Event::dispatch(new CustomerUpdated($customer));

        return $customer;
    }
}
