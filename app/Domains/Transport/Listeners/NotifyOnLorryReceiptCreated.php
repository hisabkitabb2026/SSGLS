<?php

namespace App\Domains\Transport\Listeners;

use App\Domains\Transport\Events\LorryReceiptCreated;
use Illuminate\Support\Facades\Log;

class NotifyOnLorryReceiptCreated
{
    public function handle(LorryReceiptCreated $event): void
    {
        Log::info('Lorry Receipt Created', ['data' => $event->data]);
    }
}
