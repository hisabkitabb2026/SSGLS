<?php

namespace App\Domains\Transport\Contracts;

use App\Domains\Transport\Models\LorryReceipt;

interface LorryReceiptRepository
{
    public function create(array $data): LorryReceipt;

    public function find(int $id): ?LorryReceipt;

    public function update(int $id, array $data): LorryReceipt;

    public function delete(int $id): bool;

    public function all($company);

    public function getByStatus(string $status, $company);
}
