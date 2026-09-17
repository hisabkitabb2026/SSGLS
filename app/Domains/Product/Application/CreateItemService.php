<?php

namespace App\Domains\Product\Application;

use App\Domains\Product\Contracts\ItemRepository;
use App\Domains\Product\Data\CreateItemData;
use App\Domains\Product\Events\ItemCreated;
use App\Domains\Product\Models\Item;
use Illuminate\Support\Facades\Event;

class CreateItemService
{
    public function __construct(
        private ItemRepository $repository,
    ) {}

    public function execute(CreateItemData $data): Item
    {
        $item = $this->repository->create($data->toArray());

        Event::dispatch(new ItemCreated($item));

        return $item;
    }
}
