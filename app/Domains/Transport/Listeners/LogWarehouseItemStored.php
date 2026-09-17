<?php

namespace App\Domains\Transport\Listeners;

use App\Domains\Transport\Events\WarehouseItemStored;
use Illuminate\Support\Facades\Log;

class LogWarehouseItemStored
{
    public function handle(WarehouseItemStored $event): void
    {
        Log::info('Warehouse Item Stored', ['data' => $event->data]);
    }
}
