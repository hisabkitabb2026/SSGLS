<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Load Trip (Truck Dispatch)
 *
 * Represents the actual truck dispatch that carries a consolidation group
 * (or a single Full-Load item) from the warehouse to the destination.
 *
 * Lifecycle: planned → dispatched → delivered | cancelled
 *
 * Future scaling:
 * // TODO: Integrate real GPS tracking API for live truck location
 * // TODO: Integrate Fastag API for toll tracking
 * // TODO: Add E-Way Bill API integration for compliance
 * // TODO: Support multi-leg trips (trip_segments table)
 */
class LoadTrip extends Model
{
    protected $guarded = ['id'];

    public const STATUS_PLANNED = 'planned';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'dispatch_date' => 'datetime',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_DISPATCHED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function consolidationGroup(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_group_id');
    }

    public function warehouseItems(): HasMany
    {
        return $this->hasMany(WarehouseItem::class, 'delivery_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(LorryPartyProfile::class, 'driver_profile_id');
    }

    public function brokerProfile(): BelongsTo
    {
        return $this->belongsTo(LorryPartyProfile::class, 'broker_profile_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeDispatched(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISPATCHED);
    }

    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByDestination(Builder $query, string $destination): Builder
    {
        return $query->where('destination_city', $destination);
    }
}
