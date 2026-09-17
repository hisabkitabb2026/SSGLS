<?php

namespace App\Domains\Invoicing\Contracts;

interface InvoiceRepository
{
    public function create(array $data);

    public function update(int $id, array $data);

    public function find(int $id);

    public function delete(int $id);

    public function all($company);

    public function getByStatus(string $status, $company);

    public function getDraft($company);

    public function getPublished($company);
}
