<?php

namespace App\Domains\Transport\Application;

use App\Domains\Transport\Contracts\LoadTripRepository;
use App\Domains\Transport\Data\CreateLoadTripData;
use App\Domains\Transport\Models\LoadTrip;

class CreateLoadTripService
{
    public function __construct(
        private LoadTripRepository $repository,
    ) {}

    public function execute(CreateLoadTripData $data): LoadTrip
    {
        return $this->repository->create($data->toArray());
    }
}
