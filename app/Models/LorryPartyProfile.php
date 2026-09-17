<?php

namespace App\Models;

use App\Traits\HasCompanyScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LorryPartyProfile extends Model
{
    use HasCompanyScopes;

    public const TYPE_OWNER = 'OWNER';

    public const TYPE_DRIVER = 'DRIVER';

    public const TYPE_BROKER = 'BROKER';

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters)->filter()->all();

        return $query
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', '%'.$search.'%')
                        ->orWhere('phone', 'LIKE', '%'.$search.'%')
                        ->orWhere('code', 'LIKE', '%'.$search.'%');
                });
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', $type);
            });
    }
}
