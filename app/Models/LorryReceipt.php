<?php

namespace App\Models;

use App\Traits\HasCompanyScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LorryReceipt extends Model
{
    use HasCompanyScopes;
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = [
        'lorryReceiptPdfUrl',
        'customerDisplayName',
        'displayAmountDue',
        'totalFromInvoices',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ownerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    public function driverCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'driver_customer_id');
    }

    public function brokerCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'broker_customer_id');
    }

    /**
     * Party profile (LorryPartyProfile) — the "Party" field on Lorry Receipts.
     * Replaces the Customer-based Party selector.
     */
    public function partyProfile(): BelongsTo
    {
        return $this->belongsTo(LorryPartyProfile::class, 'party_profile_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getLorryReceiptPdfUrlAttribute(): string
    {
        return url('/lorry-receipts/pdf/'.$this->unique_hash);
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(function ($q) use ($term) {
                $q->where('lorry_no', 'LIKE', '%'.$term.'%')
                    ->orWhere('contract_no', 'LIKE', '%'.$term.'%')
                    ->orWhere('owner_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('driver_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('from_name', 'LIKE', '%'.$term.'%')
                    ->orWhere('to_name', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeApplyFilters($query, array $filters)
    {
        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->whereSearch($search);
        })->when($filters['from_date'] ?? null, function ($query, $fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        })->when($filters['to_date'] ?? null, function ($query, $toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        })->when($filters['owner_customer_id'] ?? null, function ($query, $ownerId) {
            $query->where('owner_customer_id', $ownerId);
        })->orderBy('created_at', 'desc');
    }

    /**
     * Customer display name for the Lorry Receipt index table.
     * Returns the name of whoever we are paying from Section C (paid_to field),
     * which could be Owner, Driver, or Broker.
     */
    public function getCustomerDisplayNameAttribute(): string
    {
        $paidTo = $this->paid_to;

        if ($paidTo === 'Owner' || $paidTo === 'OWNER') {
            return $this->owner_name ?? $this->ownerCustomer?->name ?? '-';
        }

        if ($paidTo === 'Driver' || $paidTo === 'DRIVER') {
            return $this->driver_name ?? $this->driverCustomer?->name ?? '-';
        }

        if ($paidTo === 'Broker' || $paidTo === 'BROKER') {
            return $this->broker_name ?? $this->brokerCustomer?->name ?? '-';
        }

        // Fallback: if paid_to is not set, try to show the owner
        return $this->owner_name ?? $this->driver_name ?? $this->broker_name ?? '-';
    }

    /**
     * Amount Due for the Lorry Receipt index table.
     * Section C advance_amount + Section E net_amount_payable (if filled).
     * If only Section C is filled, return advance_amount.
     * If both Section C and E are filled, return advance_amount + net_amount_payable.
     *
     * Money columns are now stored as integer cents (consistent with
     * Invoice, Estimate, Payment models) after the migration
     * `2026_08_10_000000_convert_lorry_receipt_money_columns_to_integer`.
     */
    public function getDisplayAmountDueAttribute(): ?int
    {
        $advanceAmount = $this->advance_amount;
        $netAmountPayable = $this->net_amount_payable;

        // If Section E is filled (has final payment data), add both
        $hasSectionE = $this->hasFinalPaymentOperation();

        if ($hasSectionE && $netAmountPayable !== null) {
            return ($advanceAmount ?? 0) + $netAmountPayable;
        }

        return $advanceAmount;
    }

    /**
     * Total from Invoices for the Lorry Receipt index table.
     * Looks up invoices whose consignment_number (in invoice_items)
     * matches the docket numbers in received_no_bilties,
     * and sums their total amounts.
     */
    public function getTotalFromInvoicesAttribute(): ?int
    {
        $docketNos = $this->received_no_bilties;

        if (empty($docketNos)) {
            return null;
        }

        // Parse comma-separated docket numbers
        $docketNumbers = array_filter(array_map('trim', explode(',', $docketNos)));

        if (empty($docketNumbers)) {
            return null;
        }

        // Find invoice items with matching consignment_number and sum their invoice totals
        $invoiceIds = InvoiceItem::where('company_id', $this->company_id)
            ->where('consignment_number', '!=', null)
            ->whereIn('consignment_number', $docketNumbers)
            ->pluck('invoice_id')
            ->unique();

        if ($invoiceIds->isEmpty()) {
            return null;
        }

        return Invoice::whereIn('id', $invoiceIds)
            ->where('company_id', $this->company_id)
            ->sum('total');
    }

    /**
     * Check if Section E (final payment) has been filled.
     *
     * Money columns are now integers (cents), so we check for non-null
     * and non-zero values directly.
     */
    private function hasFinalPaymentOperation(): bool
    {
        $finalFields = [
            $this->detention_amount,
            $this->extra_hire_amount,
            $this->final_other_amount,
            $this->less_advance_other_branch_amount,
            $this->less_deduction_claims_amount,
        ];

        foreach ($finalFields as $field) {
            if ($field !== null && $field !== 0) {
                return true;
            }
        }

        return false;
    }

    protected static function booted(): void
    {
        static::creating(function (self $receipt) {
            if (empty($receipt->unique_hash)) {
                $receipt->unique_hash = Str::uuid();
            }
        });
    }
}
