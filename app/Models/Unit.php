<?php

namespace App\Models;

use App\Traits\HasCompanyScopes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasCompanyScopes;
    use HasFactory;

    protected $fillable = ['name', 'company_id'];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWhereUnit(Builder $query, int $unit_id): void
    {
        $query->orWhere('id', $unit_id);
    }

    public function scopeWhereSearch(Builder $query, string $search): Builder
    {
        return $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('unit_id')) {
            $query->whereUnit($filters->get('unit_id'));
        }

        if ($filters->get('company_id')) {
            $query->whereCompanyId($filters->get('company_id'));
        }

        return $query;
    }
}
