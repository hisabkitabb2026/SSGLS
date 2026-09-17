<?php

namespace App\Domains\Transport\Http\Controllers;

use App\Domains\Transport\Http\Requests\StoreWarehouseItemRequest;
use App\Domains\Transport\Http\Requests\UpdateWarehouseItemRequest;
use App\Domains\Transport\Http\Resources\WarehouseItemResource;
use App\Domains\Transport\Models\WarehouseItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WarehouseItemController extends Controller
{
    public function index()
    {
        $items = WarehouseItem::where('company_id', auth()->user()->company_id)->paginate();

        return WarehouseItemResource::collection($items);
    }

    public function store(StoreWarehouseItemRequest $request)
    {
        $item = WarehouseItem::create($request->validated());

        return new WarehouseItemResource($item);
    }

    public function show(WarehouseItem $item)
    {
        return new WarehouseItemResource($item);
    }

    public function update(UpdateWarehouseItemRequest $request, WarehouseItem $item)
    {
        $item->update($request->validated());

        return new WarehouseItemResource($item);
    }

    public function destroy(WarehouseItem $item)
    {
        $item->delete();

        return response()->noContent();
    }

    public function dashboard()
    {
        return [
            'total_items' => WarehouseItem::count(),
            'stored_items' => WarehouseItem::where('status', 'stored')->count(),
            'in_transit' => WarehouseItem::where('status', 'in_transit')->count(),
        ];
    }

    public function updateStatus(WarehouseItem $item, Request $request)
    {
        $item->update(['status' => $request->input('status')]);

        return new WarehouseItemResource($item);
    }

    public function byDestination($destination)
    {
        $items = WarehouseItem::where('destination', $destination)
            ->where('company_id', auth()->user()->company_id)->paginate();

        return WarehouseItemResource::collection($items);
    }
}
