<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->get();

        $companyFormat = '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>';

        $shippingFormat = '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>';

        $billingFormat = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>';

        $quotationEmailBody = "Hello {CONTACT_DISPLAY_NAME},\n\nPlease find your quotation {QUOTATION_NUMBER} attached below.\n\nQuotation Date: {QUOTATION_DATE}\nValid Until: {QUOTATION_EXPIRY_DATE}\n\nBest regards";

        $lrReceiptEmailBody = "Hello {CONTACT_DISPLAY_NAME},\n\nPlease find your LR Receipt {LR_RECEIPT_NUMBER} attached below.\n\nReceipt Date: {LR_RECEIPT_DATE}\n\nBest regards";

        $lorryReceiptEmailBody = "Hello {CONTACT_DISPLAY_NAME},\n\nPlease find your Lorry Receipt {LORRY_RECEIPT_NUMBER} attached below.\n\nReceipt Date: {LORRY_RECEIPT_DATE}\n\nBest regards";

        $officeInvoiceEmailBody = "Hello {CONTACT_DISPLAY_NAME},\n\nPlease find your Office Invoice {OFFICE_INVOICE_NUMBER} attached below.\n\nInvoice Date: {OFFICE_INVOICE_DATE}\n\nBest regards";

        foreach ($companies as $company) {
            // Quotation settings
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'quotation_company_address_format'],
                ['value' => $companyFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'quotation_shipping_address_format'],
                ['value' => $shippingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'quotation_billing_address_format'],
                ['value' => $billingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'quotation_mail_body'],
                ['value' => $quotationEmailBody]
            );

            // LR Receipt settings
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lr_receipt_company_address_format'],
                ['value' => $companyFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lr_receipt_shipping_address_format'],
                ['value' => $shippingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lr_receipt_billing_address_format'],
                ['value' => $billingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lr_receipt_mail_body'],
                ['value' => $lrReceiptEmailBody]
            );

            // Lorry Receipt settings
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lorry_receipt_company_address_format'],
                ['value' => $companyFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lorry_receipt_shipping_address_format'],
                ['value' => $shippingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lorry_receipt_billing_address_format'],
                ['value' => $billingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'lorry_receipt_mail_body'],
                ['value' => $lorryReceiptEmailBody]
            );

            // Office Invoice settings
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'office_invoice_company_address_format'],
                ['value' => $companyFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'office_invoice_shipping_address_format'],
                ['value' => $shippingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'office_invoice_billing_address_format'],
                ['value' => $billingFormat]
            );
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $company->id, 'option' => 'office_invoice_mail_body'],
                ['value' => $officeInvoiceEmailBody]
            );
        }
    }

    public function down(): void
    {
        DB::table('company_settings')->whereIn('option', [
            'quotation_company_address_format',
            'quotation_shipping_address_format',
            'quotation_billing_address_format',
            'quotation_mail_body',
            'lr_receipt_company_address_format',
            'lr_receipt_shipping_address_format',
            'lr_receipt_billing_address_format',
            'lr_receipt_mail_body',
            'lorry_receipt_company_address_format',
            'lorry_receipt_shipping_address_format',
            'lorry_receipt_billing_address_format',
            'lorry_receipt_mail_body',
            'office_invoice_company_address_format',
            'office_invoice_shipping_address_format',
            'office_invoice_billing_address_format',
            'office_invoice_mail_body',
        ])->delete();
    }
};
