<?php

namespace App\Domains\Settings\Adapters;

use App\Domains\Settings\Contracts\TaxRepository;
use App\Domains\Settings\Models\Tax;

class EloquentTaxRepository implements TaxRepository
{
    public function create(array $data)
    {
        return Tax::create($data);
    }

    public function update(int $id, array $data)
    {
        $tax = Tax::findOrFail($id);
        $tax->update($data);

        return $tax;
    }

    public function find(int $id)
    {
        return Tax::findOrFail($id);
    }

    public function delete(int $id)
    {
        return Tax::destroy($id);
    }

    public function all($company)
    {
        return Tax::where('company_id', $company)->get();
    }
}
