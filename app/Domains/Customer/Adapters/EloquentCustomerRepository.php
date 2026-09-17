<?php

namespace App\Domains\Customer\Adapters;

use App\Domains\Customer\Contracts\CustomerRepository;
use App\Domains\Customer\Models\Customer;
use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;

class EloquentCustomerRepository implements CustomerRepository
{
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(int $id, array $data): Customer
    {
        $customer = Customer::findOrFail($id);
        $customer->update($data);

        return $customer;
    }

    public function find(int $id): Customer
    {
        return Customer::findOrFail($id);
    }

    public function delete(int $id): bool
    {
        return Customer::destroy($id) > 0;
    }

    public function all($company): Collection
    {
        return Customer::where('company_id', $company instanceof Company ? $company->id : $company)->get();
    }
}
