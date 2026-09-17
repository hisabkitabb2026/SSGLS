<?php

namespace App\Models;

use App\Jobs\GenerateDocumentPdfJob;
use App\Services\Document\PaymentService;
use App\Traits\GeneratesPdfTrait;
use App\Traits\HasCompanyScopes;
use App\Traits\HasCustomFieldsTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Payment extends Model implements HasMedia
{
    use GeneratesPdfTrait;
    use HasCompanyScopes;
    use HasCustomFieldsTrait;
    use HasFactory;
    use InteractsWithMedia;

    protected $dates = ['created_at', 'updated_at', 'payment_date'];

    protected $guarded = ['id'];

    protected $appends = [
        'formattedCreatedAt',
        'formattedPaymentDate',
        'paymentPdfUrl',
    ];

    protected function casts(): array
    {
        return [
            'notes' => 'string',
            'exchange_rate' => 'float',
        ];
    }

    protected static function booted()
    {
        static::created(function ($payment) {
            GenerateDocumentPdfJob::dispatch($payment, 'payment', 'payment_number');
        });

        static::updated(function ($payment) {
            GenerateDocumentPdfJob::dispatch($payment, 'payment', 'payment_number', true);
        });
    }

    public function setSettingsAttribute($value)
    {
        if ($value) {
            $this->attributes['settings'] = json_encode($value);
        }
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->created_at)->translatedFormat($dateFormat);
    }

    public function getFormattedPaymentDateAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->company_id);

        return Carbon::parse($this->payment_date)->translatedFormat($dateFormat);
    }

    public function getPaymentPdfUrlAttribute()
    {
        return url('/payments/pdf/'.$this->unique_hash);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function emailLogs(): MorphMany
    {
        return $this->morphMany('App\Models\EmailLog', 'mailable');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->whereHas('customer', function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('contact_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('company_name', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopePaymentNumber($query, $paymentNumber)
    {
        return $query->where('payments.payment_number', 'LIKE', '%'.$paymentNumber.'%');
    }

    public function scopePaymentMethod($query, $paymentMethodId)
    {
        return $query->where('payments.payment_method_id', $paymentMethodId);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('payment_number')) {
            $query->paymentNumber($filters->get('payment_number'));
        }

        if ($filters->get('payment_id')) {
            $query->wherePayment($filters->get('payment_id'));
        }

        if ($filters->get('payment_method_id')) {
            $query->paymentMethod($filters->get('payment_method_id'));
        }

        if ($filters->get('customer_id')) {
            $query->whereCustomer($filters->get('customer_id'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->paymentsBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'desc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopePaymentsBetween($query, $start, $end)
    {
        return $query->whereBetween(
            'payments.payment_date',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWherePayment($query, $payment_id)
    {
        $query->orWhere('id', $payment_id);
    }

    public function getPDFData(): mixed
    {
        return app(PaymentService::class)->getPdfData($this);
    }

    /**
     * Payment uses 'payment' as the CompanySetting key prefix.
     */
    protected function documentTypePrefix(): string
    {
        return 'payment';
    }

    /**
     * Override: Payment uses a non-standard key for the billing address format.
     */
    public function getCustomerBillingAddress(): string|false
    {
        if ($this->customer && (! $this->customer->billingAddress()->exists())) {
            return false;
        }

        $format = CompanySetting::getSetting('payment_from_customer_address_format', $this->company_id);

        return $this->getFormattedString($format);
    }

    public function getExtraFields(): array
    {
        return [
            '{PAYMENT_DATE}' => $this->formattedPaymentDate,
            '{PAYMENT_MODE}' => $this->paymentMethod ? $this->paymentMethod->name : null,
            '{PAYMENT_NUMBER}' => $this->payment_number,
            '{PAYMENT_AMOUNT}' => format_money_pdf($this->amount, $this->customer->currency),
        ];
    }
}
