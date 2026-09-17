<?php

namespace App\Domains\Customer\Contracts;

use App\Domains\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

interface CustomerRepository
{
    public function create(array $data): Customer;

    public function update(int $id, array $data): Customer;

    public function find(int $id): Customer;

    public function delete(int $id): bool;

    public function all($company): Collection;
}
