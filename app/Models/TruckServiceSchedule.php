<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckServiceSchedule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_service_date' => 'date', 'next_due_date' => 'date', 'is_active' => 'boolean'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
