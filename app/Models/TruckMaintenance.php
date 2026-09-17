<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TruckMaintenance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
