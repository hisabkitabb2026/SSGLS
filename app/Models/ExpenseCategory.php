<?php

namespace App\Models;

use App\Traits\HasCompanyScopes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasCompanyScopes;
    use HasFactory;

    protected $fillable = ['name', 'company_id', 'description'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['amount', 'formattedCreatedAt'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getFormattedCreatedAtAttribute(mixed $value): string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function getAmountAttribute(): float
    {
        return $this->expenses()->sum('amount');
    }

    public function scopeWhereCategory(Builder $query, int $category_id): void
    {
        $query->orWhere('id', $category_id);
    }

    public function scopeWhereSearch(Builder $query, string $search): void
    {
        $query->where('name', 'LIKE', '%'.$search.'%');
    }

    /**
     * Apply multiple filter conditions including category, company, and search.
     */
    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);

        if ($filters->get('category_id')) {
            $query->whereCategory($filters->get('category_id'));
        }

        if ($filters->get('company_id')) {
            $query->whereCompanyId($filters->get('company_id'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }
}
