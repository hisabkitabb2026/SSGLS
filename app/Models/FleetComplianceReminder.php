<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetComplianceReminder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_on' => 'date', 'sent_on' => 'date'];
    }
}
