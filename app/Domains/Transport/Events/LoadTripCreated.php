<?php

namespace App\Domains\Transport\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoadTripCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public $data) {}
}
