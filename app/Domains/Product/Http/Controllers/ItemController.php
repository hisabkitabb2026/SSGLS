<?php

namespace App\Domains\Product\Http\Controllers;

use App\Domains\Product\Application\CreateItemService;
use App\Domains\Product\Contracts\ItemRepository;
use App\Domains\Product\Data\CreateItemData;
use App\Domains\Product\Http\Requests\StoreItemRequest;
use App\Domains\Product\Http\Resources\ItemResource;
use App\Domains\Product\Models\Item;
use App\Http\Controllers\Controller;

class ItemController extends Controller
{
    public function __construct(
        private ItemRepository $repository,
        private CreateItemService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Item::class);
        $items = $this->repository->all();

        return ItemResource::collection($items);
    }

    public function store(StoreItemRequest $request)
    {
        $this->authorize('create', Item::class);
        $item = $this->service->execute(
            new CreateItemData(
                name: $request->validated('name'),
                code: $request->validated('code'),
                unit_id: $request->validated('unit_id'),
                company_id: auth()->user()->company_id,
            )
        );

        return new ItemResource($item);
    }

    public function show(Item $item)
    {
        $this->authorize('view', $item);

        return new ItemResource($item);
    }

    public function update(StoreItemRequest $request, Item $item)
    {
        $this->authorize('update', $item);
        $updated = $this->repository->update($item->id, $request->validated());

        return new ItemResource($updated);
    }

    public function destroy(Item $item)
    {
        $this->authorize('delete', $item);
        $this->repository->delete($item->id);

        return response()->noContent();
    }
}
