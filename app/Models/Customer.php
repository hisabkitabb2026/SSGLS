<?php

namespace App\Models;

use App\Notifications\CustomerMailResetPasswordNotification;
use App\Traits\HasCompanyScopes;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasCompanyScopes;
    use HasCustomFieldsTrait;
    use HasFactory;
    use HasRolesAndAbilities;
    use InteractsWithMedia;
    use Notifiable;

    const TYPE_CUSTOMER = 'CUSTOMER';   // Consignor

    const TYPE_CONSIGNEE = 'CONSIGNEE'; // Consignee

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = [
        'currency',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'avatar',
    ];

    protected function casts(): array
    {
        return [
            'enable_portal' => 'boolean',
        ];
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id) ?? 'd M Y';

        return Carbon::parse($this->created_at)->translatedFormat($dateFormat);
    }

    public function setPasswordAttribute($value)
    {
        if ($value != null) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'creator_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::BILLING_TYPE);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::SHIPPING_TYPE);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new CustomerMailResetPasswordNotification($token));
    }

    public function getAvatarAttribute()
    {
        $avatar = $this->getMedia('customer_avatar')->first();

        if ($avatar) {
            return asset($avatar->getUrl());
        }

        return 0;
    }

    public function scopeWhereContactName($query, $contactName)
    {
        return $query->where('contact_name', 'LIKE', '%'.$contactName.'%');
    }

    public function scopeWhereDisplayName($query, $displayName)
    {
        return $query->where('name', 'LIKE', '%'.$displayName.'%');
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('email', 'LIKE', '%'.$term.'%')
                    ->orWhere('phone', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeWherePhone($query, $phone)
    {
        return $query->where('phone', 'LIKE', '%'.$phone.'%');
    }

    public function scopeWhereCustomer($query, $customer_id)
    {
        $query->orWhere('customers.id', $customer_id);
    }

    public function scopeApplyInvoiceFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->invoicesBetween($start, $end);
        }
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        $query->whereHas('invoices', function ($query) use ($start, $end) {
            $query->whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            );
        });
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('contact_name')) {
            $query->whereContactName($filters->get('contact_name'));
        }

        if ($filters->get('display_name')) {
            $query->whereDisplayName($filters->get('display_name'));
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('phone')) {
            $query->wherePhone($filters->get('phone'));
        }

        if ($filters->get('type')) {
            $query->where('type', $filters->get('type'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'name';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }
}
