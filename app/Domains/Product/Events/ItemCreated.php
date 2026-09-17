<?php

namespace App\Domains\Product\Events;

use App\Domains\Product\Models\Item;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ItemCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Item $item) {}
}
