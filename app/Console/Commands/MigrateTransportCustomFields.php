<?php

namespace App\Console\Commands;

use App\Models\CustomFieldValue;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Console\Command;

/**
 * Copies existing transport custom field values from the
 * custom_field_values table to the new native columns on the
 * invoices and invoice_items tables.
 *
 * This is a one-time migration command. Run it after the
 * add_transport_fields_to_invoices_table and
 * add_transport_fields_to_invoice_items_table migrations have been applied.
 *
 * Usage: php artisan transport:migrate-custom-fields
 */
class MigrateTransportCustomFields extends Command
{
    protected $signature = 'transport:migrate-custom-fields';

    protected $description = 'Migrate transport custom field values to native columns on invoices and invoice_items';

    /**
     * Maps custom field labels to native column names on the invoices table.
     */
    private array $invoiceFieldMap = [
        // LR Receipt: Trip Details
        'From' => 'from_code',
        'To' => 'to_code',
        'Truck No' => 'truck_no',
        'Mode of Payment' => 'mode_of_payment',
        'GST Tax Payable By' => 'gst_tax_payable_by',
        // LR Receipt: Consignment Details
        'Description of Goods' => 'description_of_goods',
        'HSN Code' => 'hsn_code',
        'E-way Bill No' => 'eway_bill_no',
        'Actual Weight' => 'actual_weight',
        'Charged Weight' => 'charged_weight',
        'No of Articles' => 'no_of_articles',
        'Packing' => 'packing',
        // LR Receipt: Freight Details
        'Basic Freight' => 'basic_freight',
        'Hamali' => 'hamali',
        'FOV' => 'fov',
        'Local Collection' => 'local_collection',
        'Door Delivery' => 'door_delivery',
        'Docket Charge' => 'docket_charge',
        'Other Charge' => 'other_charge',
        'Net Amount' => 'net_amount',
        // Lorry Receipt: Trip Details
        'No Of Pages' => 'no_of_pages',
        'No Of Packages' => 'no_of_packages',
        // Lorry Receipt: Vehicle Details
        'Lorry No' => 'truck_no',
        'Regd at' => 'regd_at',
        'Body Type' => 'body_type',
        'Make' => 'make',
        'Model' => 'vehicle_model',
        'Colour' => 'colour',
        'Chasis No' => 'chasis_no',
        'Engine No' => 'engine_no',
        // Lorry Receipt: Hire Particulars
        'Paid To' => 'paid_to',
        'Lorry Hire' => 'lorry_hire_amount',
        'Add Other Charges' => 'other_charges_amount',
        'Advance Paid by Cash/Cheque No' => 'advance_cash_cheque_no',
        'Advance On' => 'advance_on',
        'Bank' => 'advance_bank',
        'Advance Paid Rs' => 'advance_amount',
        'Balance Payable at' => 'balance_payable_at',
        'Loaded By' => 'loaded_by',
        // Lorry Receipt: Final Payment Details
        'Final Paid To' => 'final_paid_to',
        'Add Detention Rs.' => 'detention_amount',
        'Extra Hire Rs' => 'extra_hire_amount',
        'Other Rs' => 'final_other_amount',
        'Less Adv. at other branch' => 'less_advance_other_branch_amount',
        'Less Deduction for Claims' => 'less_deduction_claims_amount',
        'Final Balance Amount Paid at' => 'final_balance_paid_at',
        'Final Balance Date' => 'final_balance_on',
        'Cash/Cheque No.' => 'final_cash_cheque_no',
        'Final Bank' => 'final_bank',
        // Lorry Receipt: Owner Details
        'Owner Name' => 'owner_name',
        'Owner Address' => 'owner_address',
        'Owner Phone No' => 'owner_phone',
        'Owner Bank Account No' => 'owner_bank_account_no',
        'Owner PAN No' => 'owner_pan_no',
        'Financer Address' => 'financer_address',
        // Lorry Receipt: Driver Details
        'Driver Name' => 'driver_name',
        'Driver Address' => 'driver_address',
        'Driver Place' => 'driver_place',
        'Driver Licence No' => 'driver_licence_no',
        'Driver Licence Date' => 'driver_licence_date',
        'Driver Licence Issued By' => 'driver_licence_issued_by',
        'Driver RTO' => 'driver_rto_address',
        'Driver Valid Up To' => 'driver_valid_up_to',
        'Driver Bank Account No' => 'driver_bank_account_no',
        // Lorry Receipt: Broker Details
        'Broker Name' => 'broker_name',
        'Broker Address' => 'broker_address',
        'Broker Pan No' => 'broker_pan_no',
        'Broker Phone No' => 'broker_phone_no',
        'Broker Bank Account No' => 'broker_bank_account_no',
        // Lorry Receipt: Advice
        'Advice Date' => 'advice_date',
        // Lorry Receipt: Destination Broker
        'Destination Broker Name' => 'destination_broker_name',
        'Destination Broker Address' => 'destination_broker_address',
    ];

    /**
     * Maps custom field labels to native column names on the invoice_items table.
     */
    private array $itemFieldMap = [
        'Consignment No' => 'consignment_number',
        'Consignment Date' => 'consignment_date',
        'From' => 'from_code',
        'To' => 'to_code',
        'Truck No' => 'truck_no',
        'Rate' => 'rate',
        'Other Charge' => 'other_charge',
        'LR Charge' => 'lr_charge',
        'DD Charge' => 'dd_charge',
        'Amount' => 'amount',
    ];

    public function handle(): int
    {
        $this->info('Migrating transport custom field values to native columns...');

        $this->migrateInvoiceFields();
        $this->migrateItemFields();

        $this->info('Migration complete!');

        return self::SUCCESS;
    }

    /**
     * Migrate Invoice-level custom field values to native columns.
     */
    private function migrateInvoiceFields(): void
    {
        $this->info('Processing Invoice-level custom fields...');

        // Get all custom field values for Invoice model type
        $cfValues = CustomFieldValue::where('custom_field_valuable_type', Invoice::class)
            ->with('customField')
            ->chunk(100, function ($chunk) {
                $invoicesToUpdate = [];

                foreach ($chunk as $cfValue) {
                    $label = $cfValue->customField?->label;

                    if (! $label) {
                        continue;
                    }

                    $column = $this->invoiceFieldMap[$label] ?? null;

                    if (! $column) {
                        continue;
                    }

                    $value = $cfValue->string_answer
                        ?? $cfValue->number_answer
                        ?? $cfValue->date_answer
                        ?? $cfValue->text_answer
                        ?? null;

                    if ($value === null) {
                        continue;
                    }

                    $invoiceId = $cfValue->custom_field_valuable_id;

                    if (! isset($invoicesToUpdate[$invoiceId])) {
                        $invoicesToUpdate[$invoiceId] = [];
                    }

                    $invoicesToUpdate[$invoiceId][$column] = $value;
                }

                foreach ($invoicesToUpdate as $invoiceId => $data) {
                    Invoice::where('id', $invoiceId)->update($data);
                }

                $this->info('  Processed '.count($invoicesToUpdate).' invoices in this batch.');
            });

        $this->info('Invoice-level migration done.');
    }

    /**
     * Migrate InvoiceItem-level custom field values to native columns.
     */
    private function migrateItemFields(): void
    {
        $this->info('Processing InvoiceItem-level custom fields...');

        CustomFieldValue::where('custom_field_valuable_type', InvoiceItem::class)
            ->with('customField')
            ->chunk(100, function ($chunk) {
                $itemsToUpdate = [];

                foreach ($chunk as $cfValue) {
                    $label = $cfValue->customField?->label;

                    if (! $label) {
                        continue;
                    }

                    $column = $this->itemFieldMap[$label] ?? null;

                    if (! $column) {
                        continue;
                    }

                    $value = $cfValue->string_answer
                        ?? $cfValue->number_answer
                        ?? $cfValue->date_answer
                        ?? $cfValue->text_answer
                        ?? null;

                    if ($value === null) {
                        continue;
                    }

                    $itemId = $cfValue->custom_field_valuable_id;

                    if (! isset($itemsToUpdate[$itemId])) {
                        $itemsToUpdate[$itemId] = [];
                    }

                    $itemsToUpdate[$itemId][$column] = $value;
                }

                foreach ($itemsToUpdate as $itemId => $data) {
                    InvoiceItem::where('id', $itemId)->update($data);
                }

                $this->info('  Processed '.count($itemsToUpdate).' invoice items in this batch.');
            });

        $this->info('InvoiceItem-level migration done.');
    }
}
