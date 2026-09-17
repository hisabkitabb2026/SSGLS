<?php

namespace App\Domains\Customer\Listeners;

use App\Domains\Customer\Events\AddressCreated;

class NotifyOnAddressCreated
{
    public function handle(AddressCreated $event): void
    {
        \Log::info('Customer Address Created', [
            'address_id' => $event->address->id,
            'city' => $event->address->city,
        ]);
    }
}
