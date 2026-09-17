<?php

namespace App\Domains\Customer\Events;

use App\Domains\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Customer $customer) {}
}
