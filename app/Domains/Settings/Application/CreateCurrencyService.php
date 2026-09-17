<?php

namespace App\Domains\Settings\Application;

use App\Domains\Settings\Contracts\CurrencyRepository;
use App\Domains\Settings\Data\CreateCurrencyData;

class CreateCurrencyService
{
    public function __construct(private CurrencyRepository $repository) {}

    public function execute(CreateCurrencyData $data)
    {
        return $this->repository->create($data->toArray());
    }
}
