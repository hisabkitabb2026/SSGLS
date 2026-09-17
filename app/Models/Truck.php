<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Spatie\MediaLibrary\HasMedia;
// use Spatie\MediaLibrary\InteractsWithMedia;

class Truck extends Model // implements HasMedia
{
    // use InteractsWithMedia;

    protected $guarded = ['id'];

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_ON_TRIP = 'on_trip';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'capacity_kg' => 'decimal:2',
            'current_odometer_km' => 'integer',
        ];
    }

    // Compliance/document management removed — not needed for current phase.
    // public function registerMediaCollections(): void
    // {
    //     foreach (['rc_document', 'insurance_document', 'fitness_document', 'permit_document'] as $collection) {
    //         $this->addMediaCollection($collection)->singleFile();
    //     }
    // }

    public function ownerProfile(): BelongsTo
    {
        return $this->belongsTo(LorryPartyProfile::class, 'owner_profile_id');
    }

    public function loadTrips(): HasMany
    {
        return $this->hasMany(LoadTrip::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(TruckMaintenance::class);
    }

    public function odometerReadings(): HasMany
    {
        return $this->hasMany(TruckOdometerReading::class);
    }

    public function serviceSchedules(): HasMany
    {
        return $this->hasMany(TruckServiceSchedule::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    // Compliance/document checks removed — not needed for current phase.
    // public function hasExpiredDocuments(): bool
    // {
    //     return collect([$this->rc_expiry_date, $this->insurance_expiry_date, $this->fitness_expiry_date, $this->permit_expiry_date])
    //         ->filter()
    //         ->contains(fn ($date) => $date->isPast());
    // }
    //
    // public function hasDocumentsDueSoon(): bool
    // {
    //     return collect([$this->rc_expiry_date, $this->insurance_expiry_date, $this->fitness_expiry_date, $this->permit_expiry_date])
    //         ->filter()
    //         ->contains(fn ($date) => $date->between(now(), now()->addDays(30)));
    // }
}
