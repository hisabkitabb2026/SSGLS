<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationStation extends Model
{
    protected $fillable = ['estimate_id', 'name', 'sequence'];

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(QuotationRate::class);
    }
}
