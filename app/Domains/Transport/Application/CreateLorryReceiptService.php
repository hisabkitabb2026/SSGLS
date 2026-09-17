<?php

namespace App\Domains\Transport\Application;

use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Data\CreateLorryReceiptData;
use App\Domains\Transport\Events\LorryReceiptCreated;
use App\Domains\Transport\Models\LorryReceipt;
use Illuminate\Support\Facades\Event;

class CreateLorryReceiptService
{
    public function __construct(
        private LorryReceiptRepository $repository,
    ) {}

    public function execute(CreateLorryReceiptData $data): LorryReceipt
    {
        $lorryReceipt = $this->repository->create($data->toArray());

        Event::dispatch(new LorryReceiptCreated($lorryReceipt));

        return $lorryReceipt;
    }
}
