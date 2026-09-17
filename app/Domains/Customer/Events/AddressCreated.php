<?php

namespace App\Domains\Customer\Events;

use App\Domains\Customer\Models\Address;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddressCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Address $address) {}
}
