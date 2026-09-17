<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LrReceiptController extends Controller
{
    /**
     * List LR Receipts (invoices with template_name = 'lr_receipt').
     *
     * Uses the custom 'view lr receipt' gate (defined in AppServiceProvider)
     * instead of $this->authorize('viewAny', Invoice::class) which would
     * check the generic 'view-invoice' ability.  LR Receipts need their
     * own role-based access control via 'view-lr-receipt'.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view lr receipt');

        $limit = $request->get('limit', 10);

        $query = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->with(['customer', 'consigneeCustomer', 'items', 'currency']);

        $query->applyFilters(array_merge($request->all(), [
            'template_name' => 'lr_receipt',
        ]));

        $invoices = $query->paginate($limit);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'total' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single LR Receipt.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->with(['customer', 'consigneeCustomer', 'items', 'items.fields', 'items.fields.customField', 'taxes', 'fields', 'currency', 'warehouseItems.loadTrip'])
            ->findOrFail($id);

        $this->authorize('view lr receipt', $invoice);

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Check if the Lorry Receipt for a given LR Receipt (docket) has both
     * Section C (advance_amount) and Section E (net_amount_payable) filled.
     *
     * Used by the Office Invoice (Invoice Receipt) create/edit form to block
     * saving when the Lorry Receipt payment details are incomplete.
     * Without both sections filled, amount_debit cannot be calculated, which
     * means the dashboard LR Profit would be inflated.
     *
     * @param  int  $id  The LR Receipt (invoice) ID
     */
    public function lorryReceiptStatus(int $id): JsonResponse
    {
        $lrReceipt = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->findOrFail($id);

        // Find the Lorry Receipt that contains this docket number in received_no_bilties
        $lorryReceipt = Invoice::where('template_name', 'lorry_receipt')
            ->where('company_id', $lrReceipt->company_id)
            ->where(function ($query) use ($lrReceipt) {
                $docketNumber = $lrReceipt->invoice_number;
                $query->where('received_no_bilties', $docketNumber)
                    ->orWhereRaw('received_no_bilties LIKE ?', ['%'.$docketNumber.'%']);
            })
            ->first();

        if (! $lorryReceipt) {
            return response()->json([
                'has_lorry_receipt' => false,
                'section_c_filled' => false,
                'section_e_filled' => false,
                'is_complete' => false,
                'lorry_receipt_id' => null,
                'lorry_receipt_number' => null,
                'docket_number' => $lrReceipt->invoice_number,
                'message' => 'No Lorry Receipt found for this docket. Please create a Lorry Receipt first before saving the Invoice Receipt.',
            ]);
        }

        $sectionCFilled = ! blank($lorryReceipt->advance_amount) && (float) $lorryReceipt->advance_amount > 0;

        // Section E is considered filled if any of the final payment fields are set.
        // net_amount_payable is a calculated field (computed by ProfitLossCalculationService
        // on save), so we check the raw Section E input fields instead.
        $sectionEFilled = ! blank($lorryReceipt->final_balance_on)
            || ! blank($lorryReceipt->final_balance_paid_at)
            || ! blank($lorryReceipt->final_cash_cheque_no)
            || ! blank($lorryReceipt->final_other_amount)
            || ! blank($lorryReceipt->detention_amount)
            || ! blank($lorryReceipt->extra_hire_amount);

        $message = null;
        if (! $sectionCFilled && ! $sectionEFilled) {
            $message = 'Lorry Receipt is missing both Section C (Advance Payment) and Section E (Final Payment). Please complete both sections before saving this Invoice Receipt.';
        } elseif (! $sectionCFilled) {
            $message = 'Lorry Receipt is missing Section C (Advance Payment). Please complete it before saving this Invoice Receipt.';
        } elseif (! $sectionEFilled) {
            $message = 'Lorry Receipt is missing Section E (Final Payment). Please complete it before saving this Invoice Receipt.';
        }

        return response()->json([
            'has_lorry_receipt' => true,
            'section_c_filled' => $sectionCFilled,
            'section_e_filled' => $sectionEFilled,
            'is_complete' => $sectionCFilled && $sectionEFilled,
            'lorry_receipt_id' => $lorryReceipt->id,
            'lorry_receipt_number' => $lorryReceipt->invoice_number,
            'docket_number' => $lrReceipt->invoice_number,
            'message' => $message,
        ]);
    }
}
