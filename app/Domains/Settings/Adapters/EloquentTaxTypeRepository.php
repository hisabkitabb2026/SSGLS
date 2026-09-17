<?php

namespace App\Domains\Settings\Adapters;

use App\Domains\Settings\Contracts\TaxTypeRepository;
use App\Domains\Settings\Models\TaxType;

class EloquentTaxTypeRepository implements TaxTypeRepository
{
    public function create(array $data)
    {
        return TaxType::create($data);
    }

    public function update(int $id, array $data)
    {
        $taxType = TaxType::findOrFail($id);
        $taxType->update($data);

        return $taxType;
    }

    public function find(int $id)
    {
        return TaxType::findOrFail($id);
    }

    public function delete(int $id)
    {
        return TaxType::destroy($id);
    }

    public function all()
    {
        return TaxType::all();
    }
}
