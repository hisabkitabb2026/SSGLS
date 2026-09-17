<?php

namespace App\Domains\Customer\Application;

use App\Domains\Customer\Contracts\CustomerRepository;
use App\Domains\Customer\Data\CreateCustomerData;
use App\Domains\Customer\Events\CustomerCreated;
use App\Domains\Customer\Models\Customer;
use Illuminate\Support\Facades\Event;

class CreateCustomerService
{
    public function __construct(
        private CustomerRepository $repository,
    ) {}

    public function execute(CreateCustomerData $data): Customer
    {
        $customer = $this->repository->create([
            'name' => $data->name,
            'email' => $data->email,
            'phone' => $data->phone,
            'customer_type' => $data->customer_type,
            'active' => $data->active,
            'company_id' => $data->company_id,
        ]);

        Event::dispatch(new CustomerCreated($customer));

        return $customer;
    }
}
