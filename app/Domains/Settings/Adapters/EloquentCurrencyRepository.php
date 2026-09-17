<?php

namespace App\Domains\Settings\Adapters;

use App\Domains\Settings\Contracts\CurrencyRepository;
use App\Domains\Settings\Models\Currency;

class EloquentCurrencyRepository implements CurrencyRepository
{
    public function create(array $data)
    {
        return Currency::create($data);
    }

    public function update(int $id, array $data)
    {
        $currency = Currency::findOrFail($id);
        $currency->update($data);

        return $currency;
    }

    public function find(int $id)
    {
        return Currency::findOrFail($id);
    }

    public function delete(int $id)
    {
        return Currency::destroy($id);
    }

    public function all()
    {
        return Currency::all();
    }

    public function getActive()
    {
        return Currency::where('is_active', true)->get();
    }
}
