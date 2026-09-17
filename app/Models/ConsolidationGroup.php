<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consolidation Group
 *
 * Represents a batch of Part-Load warehouse items destined for the same route,
 * accumulated in the warehouse until enough weight is gathered to fill a truck.
 *
 * Lifecycle: open → ready → dispatched → completed | cancelled
 *
 * Future scaling:
 * - Multi-leg consolidation (group split across multiple trucks)
 * - AI-based consolidation recommendations
 * - Auto-fill threshold configuration per route
 */
class ConsolidationGroup extends Model
{
    protected $guarded = ['id'];

    public const STATUS_OPEN = 'open';

    public const STATUS_READY = 'ready';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'total_weight_kg' => 'decimal:2',
            'truck_capacity_kg' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_READY,
            self::STATUS_DISPATCHED,
            self::STATUS_COMPLETED,
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

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseItem::class, 'consolidation_id');
    }

    public function loadTrips(): HasMany
    {
        return $this->hasMany(LoadTrip::class, 'consolidation_group_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeByDestination(Builder $query, string $destination): Builder
    {
        return $query->where('destination_city', $destination);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    /**
     * Fill percentage: how full the truck would be if dispatched now.
     */
    public function getFillPercentageAttribute(): float
    {
        if ($this->truck_capacity_kg <= 0) {
            return 0;
        }

        return round((float) $this->total_weight_kg / (float) $this->truck_capacity_kg * 100, 1);
    }

    /**
     * Whether the group has reached a dispatch-ready fill threshold (default 80%).
     */
    public function getIsReadyToDispatchAttribute(): bool
    {
        return $this->fill_percentage >= 80;
    }
}
