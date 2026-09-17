<?php

namespace App\Domains\Transport\Adapters;

use App\Domains\Transport\Contracts\ConsolidationRepository;
use App\Domains\Transport\Models\ConsolidationGroup;

class EloquentConsolidationRepository implements ConsolidationRepository
{
    public function create(array $data)
    {
        return ConsolidationGroup::create($data);
    }

    public function update(int $id, array $data)
    {
        $consolidation = ConsolidationGroup::findOrFail($id);
        $consolidation->update($data);

        return $consolidation;
    }

    public function find(int $id)
    {
        return ConsolidationGroup::findOrFail($id);
    }

    public function delete(int $id)
    {
        return ConsolidationGroup::destroy($id);
    }

    public function all($company)
    {
        return ConsolidationGroup::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return ConsolidationGroup::where('company_id', $company->id)
            ->where('status', $status)
            ->get();
    }
}
