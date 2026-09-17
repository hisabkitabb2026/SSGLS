<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill Default Formats (email body, address formats, email attachment)
 * for transport receipt types (office_invoice, lr_receipt, lorry_receipt)
 * and quotations on existing companies.
 *
 * New companies get these via CompanyService::setupDefaultSettings(), but
 * companies created before the per-type Default Formats were added have no
 * values — the Customization tabs show empty fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        $billingAddressFormat = '<h3>{BILLING_ADDRESS_NAME}</h3><p>{BILLING_ADDRESS_STREET_1}</p><p>{BILLING_ADDRESS_STREET_2}</p><p>{BILLING_CITY}  {BILLING_STATE}</p><p>{BILLING_COUNTRY}  {BILLING_ZIP_CODE}</p><p>{BILLING_PHONE}</p>';
        $shippingAddressFormat = '<h3>{SHIPPING_ADDRESS_NAME}</h3><p>{SHIPPING_ADDRESS_STREET_1}</p><p>{SHIPPING_ADDRESS_STREET_2}</p><p>{SHIPPING_CITY}  {SHIPPING_STATE}</p><p>{SHIPPING_COUNTRY}  {SHIPPING_ZIP_CODE}</p><p>{SHIPPING_PHONE}</p>';
        $companyAddressFormat = '<h3><strong>{COMPANY_NAME}</strong></h3><p>{COMPANY_ADDRESS_STREET_1}</p><p>{COMPANY_ADDRESS_STREET_2}</p><p>{COMPANY_CITY} {COMPANY_STATE}</p><p>{COMPANY_COUNTRY}  {COMPANY_ZIP_CODE}</p><p>{COMPANY_PHONE}</p>';

        $defaults = [
            // Office Invoice
            'office_invoice_mail_body' => 'You have received a new invoice from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
            'office_invoice_company_address_format' => $companyAddressFormat,
            'office_invoice_shipping_address_format' => $shippingAddressFormat,
            'office_invoice_billing_address_format' => $billingAddressFormat,
            'office_invoice_email_attachment' => 'NO',

            // LR Receipt
            'lr_receipt_mail_body' => 'You have received a new Lorry Receipt from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
            'lr_receipt_company_address_format' => $companyAddressFormat,
            'lr_receipt_shipping_address_format' => $shippingAddressFormat,
            'lr_receipt_billing_address_format' => $billingAddressFormat,
            'lr_receipt_email_attachment' => 'NO',

            // Lorry Receipt
            'lorry_receipt_mail_body' => 'You have received a new Lorry Receipt from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
            'lorry_receipt_company_address_format' => $companyAddressFormat,
            'lorry_receipt_shipping_address_format' => $shippingAddressFormat,
            'lorry_receipt_billing_address_format' => $billingAddressFormat,
            'lorry_receipt_email_attachment' => 'NO',

            // Quotation
            'quotation_mail_body' => 'You have received a new quotation from <b>{COMPANY_NAME}</b>.</br> Please download using the button below:',
            'quotation_company_address_format' => $companyAddressFormat,
            'quotation_shipping_address_format' => $shippingAddressFormat,
            'quotation_billing_address_format' => $billingAddressFormat,
            'quotation_email_attachment' => 'NO',
        ];

        foreach (Company::all() as $company) {
            foreach ($defaults as $key => $value) {
                // Only insert if the setting doesn't already exist for this company
                CompanySetting::firstOrCreate(
                    [
                        'option' => $key,
                        'company_id' => $company->id,
                    ],
                    [
                        'option' => $key,
                        'company_id' => $company->id,
                        'value' => $value,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Cannot reverse — removing settings would break existing company configurations
    }
};
