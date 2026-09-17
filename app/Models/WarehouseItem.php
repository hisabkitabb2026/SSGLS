<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WarehouseItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const STATUS_STORED = 'stored';

    public const STATUS_PICKED = 'picked_for_consolidation';

    public const STATUS_LOADED = 'loaded_on_vehicle';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const LOAD_TYPE_FULL = 'full_load';

    public const LOAD_TYPE_PART = 'part_load';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_CRITICAL = 'critical';

    protected function casts(): array
    {
        return [
            'date_received' => 'datetime',
            'promised_dispatch_date' => 'date',
            'weight_kg' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_STORED,
            self::STATUS_PICKED,
            self::STATUS_LOADED,
            self::STATUS_IN_TRANSIT,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lr(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'lr_id');
    }

    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(ConsolidationGroup::class, 'consolidation_id');
    }

    public function loadTrip(): BelongsTo
    {
        return $this->belongsTo(LoadTrip::class, 'delivery_id');
    }

    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_warehouse_item_id');
    }

    public function splitItems(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_warehouse_item_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────────────────

    public function getDaysInWarehouseAttribute(): ?int
    {
        if (! $this->date_received) {
            return null;
        }

        return now()->startOfDay()->diffInDays(Carbon::parse($this->date_received)->startOfDay());
    }

    /**
     * Whether the item's promised dispatch date has passed and it's still in the warehouse.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (! $this->promised_dispatch_date) {
            return false;
        }

        return $this->promised_dispatch_date < now()->toDateString()
            && in_array($this->status, [self::STATUS_STORED, self::STATUS_PICKED]);
    }

    /**
     * Days remaining until the promised dispatch deadline.
     * Negative = overdue.
     */
    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (! $this->promised_dispatch_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays(Carbon::parse($this->promised_dispatch_date)->startOfDay(), false);
    }

    /**
     * Aging bucket for dashboard grouping.
     */
    public function getAgingBucketAttribute(): string
    {
        $days = $this->days_in_warehouse ?? 0;

        if ($days <= 3) {
            return '0-3';
        }
        if ($days <= 7) {
            return '4-7';
        }
        if ($days <= 15) {
            return '8-15';
        }

        return '15+';
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeStored(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_STORED);
    }

    public function scopeByDestination(Builder $query, string $destination): Builder
    {
        return $query->where('destination_city', $destination);
    }

    public function scopeByLocation(Builder $query, string $location): Builder
    {
        return $query->where('warehouse_location', $location);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByLoadType(Builder $query, string $loadType): Builder
    {
        return $query->where('load_type', $loadType);
    }

    public function scopeReadyForConsolidation(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_STORED)
            ->whereNull('consolidation_id');
    }

    /**
     * Items past their promised dispatch date that are still in the warehouse.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('promised_dispatch_date')
            ->where('promised_dispatch_date', '<', now()->toDateString())
            ->whereIn('status', [self::STATUS_STORED, self::STATUS_PICKED]);
    }

    /**
     * Items not yet assigned to any consolidation group.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('consolidation_id');
    }
}
