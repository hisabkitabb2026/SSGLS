<?php

namespace App\Models;

use App\Traits\HasCompanyScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasCompanyScopes;
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    public const TYPE_GENERAL = 'GENERAL';

    public const TYPE_MODULE = 'MODULE';

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'use_test_env' => 'boolean',
        ];
    }

    public function setSettingsAttribute($value)
    {
        $this->attributes['settings'] = json_encode($value);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeWherePaymentMethod($query, $payment_id)
    {
        $query->orWhere('id', $payment_id);
    }

    public function scopeWhereSearch($query, $search)
    {
        $query->where('name', 'LIKE', '%'.$search.'%');
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('method_id')) {
            $query->wherePaymentMethod($filters->get('method_id'));
        }

        if ($filters->get('company_id')) {
            $query->whereCompanyId($filters->get('company_id'));
        }

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }
    }

    /**
     * Create a new payment method from a validated form request.
     */
    public static function createPaymentMethod(mixed $request): self
    {
        $data = $request->getPaymentMethodPayload();

        $paymentMethod = self::create($data);

        return $paymentMethod;
    }

    /**
     * Retrieve the settings array for a payment method by its ID.
     */
    public static function getSettings(int $id): mixed
    {
        $settings = PaymentMethod::find($id)
            ->settings;

        return $settings;
    }
}
