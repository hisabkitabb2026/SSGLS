<?php

namespace App\Domains\Transport\Contracts;

interface ConsolidationRepository
{
    public function create(array $data);

    public function update(int $id, array $data);

    public function find(int $id);

    public function delete(int $id);

    public function all($company);

    public function getByStatus(string $status, $company);
}
