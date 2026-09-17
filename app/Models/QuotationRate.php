<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationRate extends Model
{
    protected $fillable = ['quotation_station_id', 'capacity', 'rate'];

    protected $casts = [
        'rate' => 'integer',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(QuotationStation::class);
    }

    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate / 100, 2);
    }
}
