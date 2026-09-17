<?php

namespace App\Domains\Transport\Application;

use App\Domains\Transport\Contracts\ConsolidationRepository;
use App\Domains\Transport\Models\ConsolidationGroup;

class ConsolidateItemsService
{
    public function __construct(
        private ConsolidationRepository $repository,
    ) {}

    public function execute(array $data): ConsolidationGroup
    {
        return $this->repository->create($data);
    }
}
