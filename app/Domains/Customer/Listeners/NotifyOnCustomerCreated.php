<?php

namespace App\Domains\Customer\Listeners;

use App\Domains\Customer\Events\CustomerCreated;

class NotifyOnCustomerCreated
{
    public function handle(CustomerCreated $event): void
    {
        \Log::info('Customer Created', [
            'customer_id' => $event->customer->id,
            'customer_name' => $event->customer->name,
        ]);
    }
}
