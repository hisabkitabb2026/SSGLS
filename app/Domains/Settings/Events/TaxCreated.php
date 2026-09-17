<?php

namespace App\Domains\Settings\Events;

use App\Domains\Settings\Models\Tax;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaxCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tax $tax) {}
}
