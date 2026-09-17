<?php

namespace App\Domains\Settings\Listeners;

use App\Domains\Settings\Events\TaxCreated;

class NotifyOnTaxCreated
{
    public function handle(TaxCreated $event): void {}
}
