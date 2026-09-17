<?php

namespace App\Domains\Transport\Listeners;

use Illuminate\Support\Facades\Log;

class CompleteDelivery
{
    public function handle($event): void
    {
        Log::info('Transport Event Triggered');
    }
}
