<?php

namespace App\Domains\Transport\Adapters;

use App\Domains\Transport\Contracts\LoadTripRepository;
use App\Domains\Transport\Models\LoadTrip;

class EloquentLoadTripRepository implements LoadTripRepository
{
    public function create(array $data)
    {
        return LoadTrip::create($data);
    }

    public function update(int $id, array $data)
    {
        $trip = LoadTrip::findOrFail($id);
        $trip->update($data);

        return $trip;
    }

    public function find(int $id)
    {
        return LoadTrip::findOrFail($id);
    }

    public function delete(int $id)
    {
        return LoadTrip::destroy($id);
    }

    public function all($company)
    {
        return LoadTrip::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return LoadTrip::where('company_id', $company->id)
            ->where('status', $status)
            ->get();
    }
}
