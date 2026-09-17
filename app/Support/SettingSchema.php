<?php

/**
 * SettingSchema — Central validation schema for company settings.
 *
 * This class defines every allowed setting key and its validation rules.
 * The UpdateSettingsRequest uses this to validate incoming settings,
 * preventing arbitrary key-value pairs from being persisted.
 *
 * To add a new setting:
 * 1. Add the key and its validation rules to the `rules()` method
 * 2. Add a default value to `defaults()` if applicable
 *
 * This is the backend counterpart to the frontend settings-registry.ts.
 */

namespace App\Support;

class SettingSchema
{
    /**
     * Get validation rules for all known settings.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            // ── Document type toggles (YES/NO) ──
            'enable_standard_invoices' => ['nullable', 'in:YES,NO'],
            'enable_invoice_receipts' => ['nullable', 'in:YES,NO'],
            'enable_estimate' => ['nullable', 'in:YES,NO'],
            'enable_quotation' => ['nullable', 'in:YES,NO'],
            'enable_lr_receipts' => ['nullable', 'in:YES,NO'],
            'enable_lorry_receipts' => ['nullable', 'in:YES,NO'],
            'enable_warehouse_items' => ['nullable', 'in:YES,NO'],
            'enable_fleet_management' => ['nullable', 'in:YES,NO'],
            'enable_customers' => ['nullable', 'in:YES,NO'],
            'enable_items' => ['nullable', 'in:YES,NO'],
            'enable_dashboard' => ['nullable', 'in:YES,NO'],
            'enable_payments' => ['nullable', 'in:YES,NO'],
            'enable_expenses' => ['nullable', 'in:YES,NO'],
            'enable_reports' => ['nullable', 'in:YES,NO'],
            'enable_members' => ['nullable', 'in:YES,NO'],

            // ── Number formats ──
            'invoice_number_format' => ['nullable', 'string', 'max:255'],
            'office_invoice_number_format' => ['nullable', 'string', 'max:255'],
            'estimate_number_format' => ['nullable', 'string', 'max:255'],
            'quotation_number_format' => ['nullable', 'string', 'max:255'],
            'payment_number_format' => ['nullable', 'string', 'max:255'],
            'lr_receipt_number_format' => ['nullable', 'string', 'max:255'],
            'lorry_receipt_number_format' => ['nullable', 'string', 'max:255'],

            // ── Email attachment toggles (YES/NO) ──
            'invoice_email_attachment' => ['nullable', 'in:YES,NO'],
            'office_invoice_email_attachment' => ['nullable', 'in:YES,NO'],
            'estimate_email_attachment' => ['nullable', 'in:YES,NO'],
            'quotation_email_attachment' => ['nullable', 'in:YES,NO'],
            'payment_email_attachment' => ['nullable', 'in:YES,NO'],
            'lr_receipt_email_attachment' => ['nullable', 'in:YES,NO'],
            'lorry_receipt_email_attachment' => ['nullable', 'in:YES,NO'],

            // ── Retrospective edits ──
            'retrospective_edits' => ['nullable', 'in:allow,disable_on_invoice_partial_paid,disable_on_invoice_paid,disable_on_invoice_sent'],
            'office_invoice_allow_edit_after_send' => ['nullable', 'in:YES,NO'],
            'lr_receipt_allow_edit_after_send' => ['nullable', 'in:YES,NO'],
            'lorry_receipt_allow_edit_after_send' => ['nullable', 'in:YES,NO'],
            'quotation_allow_edit_after_send' => ['nullable', 'in:YES,NO'],
            'payment_allow_edit_after_send' => ['nullable', 'in:YES,NO'],

            // ── Due date (invoices) ──
            'invoice_set_due_date_automatically' => ['nullable', 'in:YES,NO'],
            'invoice_due_date_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // ── Due date (office invoices / invoice receipts) ──
            'office_invoice_set_due_date_automatically' => ['nullable', 'in:YES,NO'],
            'office_invoice_due_date_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // ── Due date (LR receipts) ──
            'lr_receipt_set_due_date_automatically' => ['nullable', 'in:YES,NO'],
            'lr_receipt_due_date_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // ── Expiry date (estimates) ──
            'estimate_set_expiry_date_automatically' => ['nullable', 'in:YES,NO'],
            'estimate_expiry_date_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // ── Expiry date (quotations) ──
            'quotation_set_expiry_date_automatically' => ['nullable', 'in:YES,NO'],
            'quotation_expiry_date_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            // ── Convert estimate ──
            'estimate_convert_action' => ['nullable', 'in:no_action,delete_estimate,mark_estimate_as_accepted'],

            // ── Default formats: email bodies ──
            'invoice_mail_body' => ['nullable', 'string'],
            'office_invoice_mail_body' => ['nullable', 'string'],
            'estimate_mail_body' => ['nullable', 'string'],
            'quotation_mail_body' => ['nullable', 'string'],
            'payment_mail_body' => ['nullable', 'string'],
            'lr_receipt_mail_body' => ['nullable', 'string'],
            'lorry_receipt_mail_body' => ['nullable', 'string'],

            // ── Default formats: address formats ──
            'invoice_company_address_format' => ['nullable', 'string'],
            'invoice_shipping_address_format' => ['nullable', 'string'],
            'invoice_billing_address_format' => ['nullable', 'string'],
            'office_invoice_company_address_format' => ['nullable', 'string'],
            'office_invoice_shipping_address_format' => ['nullable', 'string'],
            'office_invoice_billing_address_format' => ['nullable', 'string'],
            'estimate_company_address_format' => ['nullable', 'string'],
            'estimate_shipping_address_format' => ['nullable', 'string'],
            'estimate_billing_address_format' => ['nullable', 'string'],
            'quotation_company_address_format' => ['nullable', 'string'],
            'quotation_shipping_address_format' => ['nullable', 'string'],
            'quotation_billing_address_format' => ['nullable', 'string'],
            'payment_company_address_format' => ['nullable', 'string'],
            'payment_from_customer_address_format' => ['nullable', 'string'],
            'lr_receipt_company_address_format' => ['nullable', 'string'],
            'lr_receipt_shipping_address_format' => ['nullable', 'string'],
            'lr_receipt_billing_address_format' => ['nullable', 'string'],
            'lorry_receipt_company_address_format' => ['nullable', 'string'],
            'lorry_receipt_shipping_address_format' => ['nullable', 'string'],
            'lorry_receipt_billing_address_format' => ['nullable', 'string'],

            // ── Default templates ──
            'default_invoice_template' => ['nullable', 'string', 'max:255'],
            'default_estimate_template' => ['nullable', 'string', 'max:255'],
            'default_office_invoice_template' => ['nullable', 'string', 'max:255'],
            'default_lr_receipt_template' => ['nullable', 'string', 'max:255'],
            'default_lorry_receipt_template' => ['nullable', 'string', 'max:255'],
            'default_payment_template' => ['nullable', 'string', 'max:255'],
            'default_quotation_template' => ['nullable', 'string', 'max:255'],

            // ── Currency (special — blocked if transactions exist) ──
            'currency' => ['nullable', 'integer', 'exists:currencies,id'],
        ];
    }

    /**
     * Get the list of all valid setting keys.
     *
     * @return array<int, string>
     */
    public static function allowedKeys(): array
    {
        return array_keys(self::rules());
    }

    /**
     * Check if a key is a valid setting key.
     */
    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::allowedKeys(), true);
    }

    /**
     * Get validation rules for a specific key.
     *
     * @return array<int, string>|null
     */
    public static function rulesForKey(string $key): ?array
    {
        $rules = self::rules();

        return $rules[$key] ?? null;
    }
}
