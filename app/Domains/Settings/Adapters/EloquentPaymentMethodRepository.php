<?php

namespace App\Domains\Settings\Adapters;

use App\Domains\Settings\Contracts\PaymentMethodRepository;
use App\Domains\Settings\Models\PaymentMethod;

class EloquentPaymentMethodRepository implements PaymentMethodRepository
{
    public function create(array $data)
    {
        return PaymentMethod::create($data);
    }

    public function update(int $id, array $data)
    {
        $method = PaymentMethod::findOrFail($id);
        $method->update($data);

        return $method;
    }

    public function find(int $id)
    {
        return PaymentMethod::findOrFail($id);
    }

    public function delete(int $id)
    {
        return PaymentMethod::destroy($id);
    }

    public function all($company)
    {
        return PaymentMethod::where('company_id', $company)->get();
    }
}
