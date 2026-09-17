<?php

namespace App\Domains\Transport\Repositories;

use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Models\LorryReceipt;

class EloquentLorryReceiptRepository implements LorryReceiptRepository
{
    public function create(array $data): LorryReceipt
    {
        return LorryReceipt::create($data);
    }

    public function find(int $id): ?LorryReceipt
    {
        return LorryReceipt::find($id);
    }

    public function update(int $id, array $data): LorryReceipt
    {
        $receipt = LorryReceipt::findOrFail($id);
        $receipt->update($data);

        return $receipt;
    }

    public function delete(int $id): bool
    {
        return (bool) LorryReceipt::destroy($id);
    }

    public function all($company)
    {
        return LorryReceipt::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return LorryReceipt::where('company_id', $company->id)
            ->where('status', $status)->get();
    }
}
