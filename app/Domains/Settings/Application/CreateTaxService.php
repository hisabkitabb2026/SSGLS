<?php

namespace App\Domains\Settings\Application;

use App\Domains\Settings\Contracts\TaxRepository;
use App\Domains\Settings\Data\CreateTaxData;

class CreateTaxService
{
    public function __construct(private TaxRepository $repository) {}

    public function execute(CreateTaxData $data)
    {
        return $this->repository->create($data->toArray());
    }
}
