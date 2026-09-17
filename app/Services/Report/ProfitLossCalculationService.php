<?php

namespace App\Services\Report;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Support\Collection;

class ProfitLossCalculationService
{
    /**
     * Recalculate amount_debit for all LR Receipts related to the given Office Invoice.
     * This is called when an Office Invoice is saved/updated.
     *
     * Only runs when Invoice Receipts are enabled (enable_invoice_receipts = YES).
     * amount_credit is populated from the Office Invoice item amounts.
     */
    public function recalculateFromOfficeInvoice(Invoice $officeInvoice): void
    {
        // Only process office_invoice templates
        if ($officeInvoice->template_name !== 'office_invoice') {
            return;
        }

        // Only populate amount_credit when Invoice Receipts are enabled
        $enableInvoiceReceipts = CompanySetting::getSetting('enable_invoice_receipts', $officeInvoice->company_id) === 'YES';

        if (! $enableInvoiceReceipts) {
            return;
        }

        // ── Business rule: LR Profit is only calculated when the customer
        // has actually PAID the Office Invoice. If the invoice is UNPAID
        // or PARTIALLY_PAID, amount_credit on the LR Receipts is cleared
        // (set to 0), which means LR Profit = 0 - amount_debit = 0 (or
        // negative if lorry owner has been paid).
        //
        // This ensures the dashboard doesn't show inflated LR Profit for
        // invoices that haven't been collected yet.
        $isPaid = $officeInvoice->paid_status === Invoice::STATUS_PAID;

        // Get all items with consignment_number (docket numbers)
        $items = $officeInvoice->items()->whereNotNull('consignment_number')->get();

        foreach ($items as $item) {
            $docketNumber = $item->consignment_number;

            // Find the LR Receipt with this docket number
            $lrReceipt = Invoice::where('template_name', 'lr_receipt')
                ->where('company_id', $officeInvoice->company_id)
                ->where('invoice_number', $docketNumber)
                ->first();

            if ($lrReceipt) {
                if ($isPaid) {
                    // Invoice is fully paid — set amount_credit from the item amount.
                    // The "amount" field on Office Invoice items stores the billing
                    // amount in rupees (e.g. 16000), while "total" is the standard
                    // invoice item total (which is 0 for transport templates).
                    // Convert to cents to match amount_credit's storage format.
                    $lrReceipt->amount_credit = (int) ((float) $item->amount * 100);
                    $lrReceipt->office_invoice_number = $officeInvoice->invoice_number;
                    $lrReceipt->save();

                    // Now recalculate amount_debit for all related Lorry Receipts
                    $this->recalculateAmountDebitForLorryReceipt($lrReceipt);
                } else {
                    // Invoice is not fully paid — clear BOTH amount_credit AND
                    // amount_debit so LR Profit = 0 (not negative). LR Profit
                    // should only be visible once the customer has actually paid.
                    $lrReceipt->amount_credit = 0;
                    $lrReceipt->amount_debit = 0;
                    $lrReceipt->office_invoice_number = $officeInvoice->invoice_number;
                    $lrReceipt->save();
                }
            }
        }
    }

    /**
     * Recalculate amount_debit for a specific LR Receipt based on its related Lorry Receipt.
     */
    public function recalculateAmountDebitForLorryReceipt(Invoice $lrReceipt): void
    {
        // Find the parent Lorry Receipt that contains this docket number
        $lorryReceipt = Invoice::where('template_name', 'lorry_receipt')
            ->where('company_id', $lrReceipt->company_id)
            ->where(function ($query) use ($lrReceipt) {
                // Check if received_no_bilties contains this LR Receipt's invoice_number.
                // Use LIKE for database-agnostic compatibility (SQLite doesn't
                // support FIND_IN_SET).
                $query->where('received_no_bilties', $lrReceipt->invoice_number)
                    ->orWhereRaw('received_no_bilties LIKE ?', ['%'.$lrReceipt->invoice_number.'%']);
            })
            ->first();

        if ($lorryReceipt) {
            $this->recalculateAmountDebitForLorryReceiptDirect($lorryReceipt);
        }
    }

    /**
     * Recalculate amount_debit for all LR Receipts related to a Lorry Receipt.
     * This is called when a Lorry Receipt is saved/updated.
     */
    public function recalculateFromLorryReceipt(Invoice $lorryReceipt): void
    {
        // Only process lorry_receipt templates
        if ($lorryReceipt->template_name !== 'lorry_receipt') {
            return;
        }

        // ── Calculate net_amount_payable from Section E fields ──
        // net_amount_payable is a derived field, not a direct form input.
        // It represents the final balance payable to the lorry owner after
        // accounting for advance (Section C) and all adjustments (Section E).
        //
        // Formula:
        //   net_amount_payable = lorry_hire_amount
        //                       - advance_amount
        //                       + detention_amount
        //                       + extra_hire_amount
        //                       + final_other_amount
        //                       - less_advance_other_branch_amount
        //                       - less_deduction_claims_amount
        //
        // This must be calculated BEFORE recalculateAmountDebitForLorryReceiptDirect()
        // because that method checks `blank($lorryReceipt->net_amount_payable)` and
        // skips the amount_debit calculation if it's blank.
        $lorryHire = (float) ($lorryReceipt->lorry_hire_amount ?? 0);
        $advanceAmount = (float) ($lorryReceipt->advance_amount ?? 0);
        $detentionAmount = (float) ($lorryReceipt->detention_amount ?? 0);
        $extraHireAmount = (float) ($lorryReceipt->extra_hire_amount ?? 0);
        $finalOtherAmount = (float) ($lorryReceipt->final_other_amount ?? 0);
        $lessAdvOtherBranch = (float) ($lorryReceipt->less_advance_other_branch_amount ?? 0);
        $lessDeductionClaims = (float) ($lorryReceipt->less_deduction_claims_amount ?? 0);

        $netAmountPayable = $lorryHire
            - $advanceAmount
            + $detentionAmount
            + $extraHireAmount
            + $finalOtherAmount
            - $lessAdvOtherBranch
            - $lessDeductionClaims;

        // Only save if the value changed (avoid unnecessary DB writes)
        if ((float) $lorryReceipt->net_amount_payable !== $netAmountPayable) {
            $lorryReceipt->net_amount_payable = (string) $netAmountPayable;
            $lorryReceipt->save();
        }

        $this->recalculateAmountDebitForLorryReceiptDirect($lorryReceipt);
    }

    /**
     * Core logic to distribute expense (amount_debit) proportionally among LR Receipts.
     *
     * Business rule (Scenario 2): If Section E (net_amount_payable) is not filled,
     * we do not have sufficient information to calculate the total transport cost.
     * In that case, we skip the calculation entirely — amount_debit on the related
     * LR Receipts is left unchanged (or 0 if never set). The calculation will be
     * performed later when Section E is filled.
     */
    public function recalculateAmountDebitForLorryReceiptDirect(Invoice $lorryReceipt): void
    {
        // Parse docket numbers from received_no_bilties
        $docketNumbers = $this->parseDocketNumbers($lorryReceipt->received_no_bilties);

        if (empty($docketNumbers)) {
            return;
        }

        // ── Scenario 2: Section E (net_amount_payable) must be filled ──
        // If Section E is not filled, we don't have the complete transport cost.
        // Skip calculation — it will be re-triggered when Section E is filled.
        if (blank($lorryReceipt->net_amount_payable)) {
            return;
        }

        // Calculate total expense (Section C + Section E) in cents.
        // Both fields are stored as strings (rupees), so cast to float then
        // convert to cents to match amount_debit's integer-cents storage format.
        $advanceAmount = (float) $lorryReceipt->advance_amount;
        $netAmountPayable = (float) $lorryReceipt->net_amount_payable;
        $totalExpense = (int) (($advanceAmount + $netAmountPayable) * 100);

        if ($totalExpense <= 0) {
            // No expense to distribute, clear amount_debit on related LR Receipts
            $this->clearAmountDebitForDockets($docketNumbers, $lorryReceipt->company_id);

            return;
        }

        // Find all LR Receipts for these dockets
        $lrReceipts = Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $lorryReceipt->company_id)
            ->whereIn('invoice_number', $docketNumbers)
            ->get();

        if ($lrReceipts->isEmpty()) {
            return;
        }

        // Calculate total income (amount_credit) for proportion calculation
        // Only consider LR Receipts that have an associated Office Invoice
        $totalIncome = $lrReceipts->filter(function ($lr) {
            return $lr->amount_credit > 0;
        })->sum('amount_credit');

        // Update challan_number on all related LR Receipts
        foreach ($lrReceipts as $lrReceipt) {
            $lrReceipt->challan_number = $lorryReceipt->contract_no ?? $lorryReceipt->invoice_number;

            // Only distribute expense if this LR Receipt has income (amount_credit > 0).
            // If amount_credit is 0 (Office Invoice not yet linked), leave amount_debit
            // unchanged — it will be recalculated when the Office Invoice is created
            // and triggers recalculateFromOfficeInvoice().
            if ($totalIncome > 0 && $lrReceipt->amount_credit > 0) {
                $proportion = $lrReceipt->amount_credit / $totalIncome;
                $lrReceipt->amount_debit = (int) ($totalExpense * $proportion);
            }

            $lrReceipt->save();
        }
    }

    /**
     * Clear amount_debit for LR Receipts when Lorry Receipt has no expense.
     */
    private function clearAmountDebitForDockets(array $docketNumbers, int $companyId): void
    {
        Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $companyId)
            ->whereIn('invoice_number', $docketNumbers)
            ->update([
                'amount_debit' => 0,
                'challan_number' => null,
            ]);
    }

    /**
     * Parse comma-separated docket numbers into an array.
     */
    private function parseDocketNumbers(?string $receivedNoBilties): array
    {
        if (empty($receivedNoBilties)) {
            return [];
        }

        return array_filter(
            array_map('trim', explode(',', $receivedNoBilties)),
            fn ($value) => ! empty($value)
        );
    }

    /**
     * Recalculate profit/loss for a specific company within a date range.
     * Returns total income, total expense, and net profit.
     */
    public function calculateProfitLoss(int $companyId, string $fromDate, string $toDate): array
    {
        // Total Income: Sum of amount_credit from LR Receipts
        $totalIncome = Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->sum('amount_credit');

        // Total Expense: Sum of amount_debit from LR Receipts
        $totalExpense = Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->sum('amount_debit');

        // Net Profit = Income - Expense
        $netProfit = $totalIncome - $totalExpense;

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $netProfit,
        ];
    }

    /**
     * Get profit/loss breakdown by customer (consignor).
     */
    public function getProfitLossByCustomer(int $companyId, string $fromDate, string $toDate): Collection
    {
        return Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->selectRaw('
                owner_customer_id,
                SUM(amount_credit) as total_income,
                SUM(amount_debit) as total_expense,
                SUM(amount_credit - amount_debit) as net_profit
            ')
            ->groupBy('owner_customer_id')
            ->get();
    }

    /**
     * Get detailed LR Receipt data for profit/loss report.
     */
    public function getLrReceiptDetails(int $companyId, string $fromDate, string $toDate): Collection
    {
        return Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->with(['ownerCustomer', 'driverCustomer', 'brokerCustomer'])
            ->get()
            ->map(function ($lrReceipt) {
                return [
                    'id' => $lrReceipt->id,
                    'lr_no' => $lrReceipt->invoice_number,
                    'lr_date' => $lrReceipt->invoice_date,
                    'challan_no' => $lrReceipt->challan_number,
                    'contract_no' => $lrReceipt->contract_no,
                    'from_code' => $lrReceipt->from_code,
                    'to_code' => $lrReceipt->to_code,
                    'owner_name' => $lrReceipt->owner_name,
                    'driver_name' => $lrReceipt->driver_name,
                    'lorry_no' => $lrReceipt->lorry_no,
                    'amount_credit' => $lrReceipt->amount_credit,
                    'amount_debit' => $lrReceipt->amount_debit,
                    'net_profit' => $lrReceipt->amount_credit - $lrReceipt->amount_debit,
                    'office_invoice_no' => $lrReceipt->office_invoice_number,
                    'owner_customer' => $lrReceipt->ownerCustomer?->name,
                    'driver_customer' => $lrReceipt->driverCustomer?->name,
                    'broker_customer' => $lrReceipt->brokerCustomer?->name,
                ];
            });
    }
}
