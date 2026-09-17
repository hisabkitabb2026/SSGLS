<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TruckOdometerReading extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('odometer_reading_image')->singleFile();
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }
}
