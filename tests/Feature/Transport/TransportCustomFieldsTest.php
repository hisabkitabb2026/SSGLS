<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;

    $this->withHeaders([
        'company' => $this->companyId,
    ]);

    Sanctum::actingAs($user, ['*']);
});

function createCustomer($companyId): Customer
{
    $companyCurrency = CompanySetting::getSetting('currency', $companyId);

    return Customer::factory()->create([
        'company_id' => $companyId,
        'currency_id' => $companyCurrency,
    ]);
}

test('custom fields index no longer auto-creates transport fields', function () {
    // After Phase 3 cleanup, the custom fields index endpoint no longer
    // auto-creates transport fields. Transport data is stored as native columns.
    $response = getJson('api/v1/custom-fields?template_name=lr_receipt&limit=all');

    $response->assertOk();

    // No transport custom fields should be auto-created
    $transportFields = CustomField::where('company_id', $this->companyId)
        ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
        ->get();

    expect($transportFields)->toBeEmpty();
});

test('lr receipt can be created with native transport columns', function () {
    $customer = createCustomer($this->companyId);

    $response = postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'LR-TEST-001',
        'template_name' => 'lr_receipt',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'from_code' => 'Vapi',
        'to_code' => 'Mumbai',
        'truck_no' => 'GJ05BC1234',
        'basic_freight' => 5000,
        'hamali' => 500,
        'net_amount' => 5500,
    ]);

    $response->assertOk();

    $invoice = Invoice::where('invoice_number', 'LR-TEST-001')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->from_code)->toBe('Vapi')
        ->and($invoice->to_code)->toBe('Mumbai')
        ->and($invoice->truck_no)->toBe('GJ05BC1234')
        ->and($invoice->basic_freight)->toBe(5000)
        ->and($invoice->hamali)->toBe(500)
        ->and($invoice->net_amount)->toBe(5500);
});

test('office invoice can be created with native item-level transport columns', function () {
    $customer = createCustomer($this->companyId);

    // First create an LR Receipt so the consignment number exists
    postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'CON-001',
        'template_name' => 'lr_receipt',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'from_code' => 'Vapi',
        'to_code' => 'Mumbai',
        'truck_no' => 'GJ05BC1234',
    ])->assertOk();

    // Now create the Office Invoice referencing that LR Receipt
    $response = postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'OI-TEST-001',
        'template_name' => 'office_invoice',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'items' => [
            [
                'name' => 'Consignment 1',
                'description' => '',
                'quantity' => 1,
                'price' => 0,
                'discount' => 0,
                'discount_val' => 0,
                'discount_type' => 'fixed',
                'tax' => 0,
                'total' => 0,
                'consignment_number' => 'CON-001',
                'from_code' => 'Vapi',
                'to_code' => 'Mumbai',
                'truck_no' => 'GJ05BC1234',
                'rate' => 1000,
                'other_charge' => 200,
                'lr_charge' => 50,
                'dd_charge' => 30,
                'amount' => 1280,
            ],
        ],
    ]);

    $response->assertOk();

    $invoice = Invoice::where('invoice_number', 'OI-TEST-001')->first();

    expect($invoice)->not->toBeNull();

    $item = InvoiceItem::where('invoice_id', $invoice->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->consignment_number)->toBe('CON-001')
        ->and($item->from_code)->toBe('Vapi')
        ->and($item->to_code)->toBe('Mumbai')
        ->and($item->truck_no)->toBe('GJ05BC1234')
        ->and($item->rate)->toBe(1000)
        ->and($item->other_charge)->toBe(200)
        ->and($item->lr_charge)->toBe(50)
        ->and($item->dd_charge)->toBe(30)
        ->and($item->amount)->toBe(1280);
});

test('lorry receipt can be created with native transport columns', function () {
    $customer = createCustomer($this->companyId);

    $response = postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'LR-RECEIPT-001',
        'template_name' => 'lorry_receipt',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'from_code' => 'Vapi',
        'to_code' => 'Bengaluru',
        'truck_no' => 'GJ05BC1234',
        'owner_name' => 'Test Owner',
        'driver_name' => 'Test Driver',
        'advance_amount' => '5000',
        'net_amount_payable' => '15000',
        'received_no_bilties' => 'LR NO 1234',
        'contract_no' => 'CT-001',
    ]);

    $response->assertOk();

    $invoice = Invoice::where('invoice_number', 'LR-RECEIPT-001')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->from_code)->toBe('Vapi')
        ->and($invoice->to_code)->toBe('Bengaluru')
        ->and($invoice->truck_no)->toBe('GJ05BC1234')
        ->and($invoice->owner_name)->toBe('Test Owner')
        ->and($invoice->driver_name)->toBe('Test Driver')
        ->and($invoice->advance_amount)->toBe('5000')
        ->and($invoice->net_amount_payable)->toBe('15000')
        ->and($invoice->received_no_bilties)->toBe('LR NO 1234')
        ->and($invoice->contract_no)->toBe('CT-001');
});

test('office invoice requires consignment number on each item', function () {
    $customer = createCustomer($this->companyId);

    $response = postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'OI-NOCONS-001',
        'template_name' => 'office_invoice',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'items' => [
            [
                'name' => 'Consignment 1',
                'description' => '',
                'quantity' => 1,
                'price' => 0,
                'discount' => 0,
                'discount_val' => 0,
                'discount_type' => 'fixed',
                'tax' => 0,
                'total' => 0,
                // consignment_number intentionally omitted
                'from_code' => 'Vapi',
                'to_code' => 'Mumbai',
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items.0.consignment_number']);
});

test('office invoice rejects consignment number that does not match any lr receipt', function () {
    $customer = createCustomer($this->companyId);

    // No LR Receipt with 'NONEXISTENT-LR' exists â€” should be rejected
    $response = postJson('api/v1/invoices', [
        'invoice_date' => '2026-07-28',
        'due_date' => '2026-08-28',
        'invoice_number' => 'OI-BADLR-001',
        'template_name' => 'office_invoice',
        'customer_id' => $customer->id,
        'discount' => 0,
        'discount_val' => 0,
        'sub_total' => 0,
        'total' => 0,
        'tax' => 0,
        'items' => [
            [
                'name' => 'Consignment 1',
                'description' => '',
                'quantity' => 1,
                'price' => 0,
                'discount' => 0,
                'discount_val' => 0,
                'discount_type' => 'fixed',
                'tax' => 0,
                'total' => 0,
                'consignment_number' => 'NONEXISTENT-LR',
                'from_code' => 'Vapi',
                'to_code' => 'Mumbai',
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items.0.consignment_number']);
});
