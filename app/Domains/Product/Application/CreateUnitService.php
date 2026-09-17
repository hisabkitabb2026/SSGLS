<?php

namespace App\Domains\Product\Application;

use App\Domains\Product\Contracts\UnitRepository;
use App\Domains\Product\Data\CreateUnitData;
use App\Domains\Product\Models\Unit;

class CreateUnitService
{
    public function __construct(
        private UnitRepository $repository,
    ) {}

    public function execute(CreateUnitData $data): Unit
    {
        return $this->repository->create($data->toArray());
    }
}
