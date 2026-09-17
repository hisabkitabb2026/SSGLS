/**
 * Settings Registry — Single Source of Truth
 *
 * This file defines every customization tab, section, and field in the system.
 * Adding a new document type = adding a new entry to `settingsTabs`.
 *
 * The registry is consumed by:
 * - CustomizationView.vue (renders tabs)
 * - DocumentTab.vue (renders sections within a tab)
 * - Each generic section component (receives config as props)
 * - The search bar (indexes titles/descriptions)
 */

// ──────────────────────────────────────────────
// Types
// ──────────────────────────────────────────────

export type SettingFieldType =
  | 'boolean'
  | 'string'
  | 'number'
  | 'enum'
  | 'template'
  | 'custom-input'

export type SectionComponentType =
  | 'number-customizer'
  | 'default-formats'
  | 'retrospective-edits'
  | 'due-date'
  | 'expiry-date'
  | 'convert-estimate'
  | 'template-uploader'
  | 'email-attachment'
  | 'item-units'
  | 'document-types'

export interface EnumOption {
  /** i18n key for the label */
  labelKey: string
  /** value stored in CompanySetting */
  value: string
}

export interface AddressFormatConfig {
  /** setting key, e.g. 'invoice_company_address_format' */
  key: string
  /** i18n key for the label */
  labelKey: string
  /** fields passed to BaseCustomInput, e.g. ['company'] or ['shipping', 'customer'] */
  customInputFields: string[]
}

export interface DefaultFormatsConfig {
  /** setting key for the email body, e.g. 'invoice_mail_body' */
  mailBodyKey: string
  /** i18n key for the email body label */
  mailBodyLabelKey: string
  /** fields for BaseCustomInput on the email body */
  mailBodyFields: string[]
  /** address format fields */
  addressFormats: AddressFormatConfig[]
}

export interface NumberCustomizerConfig {
  /** type passed to the store, e.g. 'invoice', 'estimate' */
  type: string
  /** default series prefix, e.g. 'INV', 'LR', 'CH' */
  defaultSeries: string
  /** setting key for the number format, e.g. 'invoice_number_format' */
  settingKey: string
}

export interface RetrospectiveConfig {
  /**
   * 'invoice' = radio group with allow/disable_on_partial_paid/disable_on_paid/disable_on_sent
   * 'toggle'  = simple YES/NO toggle using {key}_allow_edit_after_send
   */
  variant: 'invoice' | 'toggle'
  /** setting key for the retrospective value */
  settingKey: string
  /** enum options (only for variant 'invoice') */
  enumOptions?: EnumOption[]
}

export interface SettingSection {
  /** unique id within the tab */
  id: string
  /** i18n key for the section title */
  titleKey: string
  /** i18n key for the description */
  descriptionKey?: string
  /** which generic component renders this section */
  component: SectionComponentType
  /** config for number-customizer sections */
  numberCustomizer?: NumberCustomizerConfig
  /** config for default-formats sections */
  defaultFormats?: DefaultFormatsConfig
  /** config for retrospective-edits sections */
  retrospective?: RetrospectiveConfig
  /** config for template-uploader sections */
  templateUploader?: {
    /** document type for the template uploader, e.g. 'invoice1', 'lr_receipt' */
    documentType: string
    /** setting key for the active template, e.g. 'default_invoice_template' */
    settingKey: string
  }
  /** config for email-attachment sections */
  emailAttachment?: {
    /** setting key, e.g. 'invoice_email_attachment' */
    settingKey: string
  }
  /** config for due-date sections */
  dueDate?: {
    /** setting key for auto toggle, e.g. 'invoice_set_due_date_automatically' */
    autoKey: string
    /** setting key for days, e.g. 'invoice_due_date_days' */
    daysKey: string
  }
  /** config for expiry-date sections */
  expiryDate?: {
    /** setting key for auto toggle */
    autoKey: string
    /** setting key for days */
    daysKey: string
  }
  /** config for convert-estimate sections */
  convertEstimate?: {
    /** setting key, e.g. 'estimate_convert_action' */
    settingKey: string
    /** enum options */
    enumOptions: EnumOption[]
  }
}

export interface SettingTab {
  /** unique id */
  id: string
  /** i18n key for the tab title */
  titleKey: string
  /**
   * If set, the tab is only visible when this setting key === 'YES'.
   * e.g. 'enable_standard_invoices' — if 'NO', the tab is hidden.
   */
  enabledByKey?: string
  /** sections within this tab */
  sections: SettingSection[]
  /**
   * Special tabs that don't use the generic DocumentTab renderer.
   * - 'document-types' = toggle list
   * - 'item-units' = CRUD table
   */
  special?: 'document-types' | 'item-units'
}

// ──────────────────────────────────────────────
// Shared Config Builders (DRY)
// ──────────────────────────────────────────────

/**
 * Builds the standard set of sections that most document types share:
 * NumberCustomizer + DefaultFormats + RetrospectiveEdits + TemplateUploader + EmailAttachment
 */
function buildDocumentSections(params: {
  numberCustomizer: NumberCustomizerConfig
  defaultFormats: DefaultFormatsConfig
  retrospective: RetrospectiveConfig
  templateUploader: { documentType: string; settingKey: string }
  emailAttachment: { settingKey: string }
}): SettingSection[] {
  return [
    {
      id: 'number-format',
      titleKey: 'settings.customization.number_format',
      descriptionKey: 'settings.customization.number_format_description',
      component: 'number-customizer',
      numberCustomizer: params.numberCustomizer,
    },
    {
      id: 'default-formats',
      titleKey: 'settings.customization.default_formats',
      descriptionKey: 'settings.customization.default_formats_description',
      component: 'default-formats',
      defaultFormats: params.defaultFormats,
    },

    {
      id: 'retrospective-edits',
      titleKey: 'settings.customization.retrospective_edits',
      descriptionKey: 'settings.customization.retrospective_edits_description',
      component: 'retrospective-edits',
      retrospective: params.retrospective,
    },
    {
      id: 'pdf-template',
      titleKey: 'settings.customization.custom_template_title',
      descriptionKey: 'settings.customization.custom_template_description',
      component: 'template-uploader',
      templateUploader: params.templateUploader,
    },
    {
      id: 'email-attachment',
      titleKey: 'settings.customization.email_attachment',
      descriptionKey: 'settings.customization.email_attachment_description',
      component: 'email-attachment',
      emailAttachment: params.emailAttachment,
    },
  ]
}

// ──────────────────────────────────────────────
// Tab Definitions
// ──────────────────────────────────────────────

export const settingsTabs: SettingTab[] = [
  // ── Tab 1: Document Types ──────────────────
  {
    id: 'document-types',
    titleKey: 'settings.customization.document_types.title',
    special: 'document-types',
    sections: [],
  },

  // ── Tab 2: Invoices ───────────────────────
  {
    id: 'invoices',
    titleKey: 'settings.customization.invoices.title',
    enabledByKey: 'enable_standard_invoices',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.invoices.invoice_number_format',
        descriptionKey: 'settings.customization.invoices.invoice_number_format_description',
        component: 'number-customizer',
        numberCustomizer: {
          type: 'invoice',
          defaultSeries: 'INV',
          settingKey: 'invoice_number_format',
        },
      },
      {
        id: 'due-date',
        titleKey: 'settings.customization.invoices.due_date',
        descriptionKey: 'settings.customization.invoices.due_date_description',
        component: 'due-date',
        dueDate: {
          autoKey: 'invoice_set_due_date_automatically',
          daysKey: 'invoice_due_date_days',
        },
      },
      {
        id: 'retrospective-edits',
        titleKey: 'settings.customization.invoices.retrospective_edits',
        descriptionKey: 'settings.customization.invoices.retrospective_edits_description',
        component: 'retrospective-edits',
        retrospective: {
          variant: 'invoice',
          settingKey: 'retrospective_edits',
          enumOptions: [
            { labelKey: 'settings.customization.invoices.allow', value: 'allow' },
            { labelKey: 'settings.customization.invoices.disable_on_invoice_partial_paid', value: 'disable_on_invoice_partial_paid' },
            { labelKey: 'settings.customization.invoices.disable_on_invoice_paid', value: 'disable_on_invoice_paid' },
            { labelKey: 'settings.customization.invoices.disable_on_invoice_sent', value: 'disable_on_invoice_sent' },
          ],
        },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.invoices.default_formats',
        descriptionKey: 'settings.customization.invoices.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'invoice_mail_body',
          mailBodyLabelKey: 'settings.customization.invoices.default_invoice_email_body',
          mailBodyFields: ['customer', 'company', 'invoice'],
          addressFormats: [
            { key: 'invoice_company_address_format', labelKey: 'settings.customization.invoices.company_address_format', customInputFields: ['company'] },
            { key: 'invoice_shipping_address_format', labelKey: 'settings.customization.invoices.shipping_address_format', customInputFields: ['shipping', 'customer'] },
            { key: 'invoice_billing_address_format', labelKey: 'settings.customization.invoices.billing_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'invoice1', settingKey: 'default_invoice_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.invoices.invoice_email_attachment',
        descriptionKey: 'settings.customization.invoices.invoice_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'invoice_email_attachment' },
      },
    ],
  },

  // ── Tab 3: Invoice Receipts (Office Invoices) ──
  {
    id: 'invoice-receipts',
    titleKey: 'settings.customization.invoice_receipts.title',
    enabledByKey: 'enable_invoice_receipts',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.office_invoices.office_invoice_number_format',
        descriptionKey: 'settings.customization.office_invoices.office_invoice_number_format_description',
        component: 'number-customizer',
        numberCustomizer: { type: 'invoice', defaultSeries: 'INV', settingKey: 'office_invoice_number_format' },
      },
      {
        id: 'due-date',
        titleKey: 'settings.customization.office_invoices.due_date',
        descriptionKey: 'settings.customization.office_invoices.due_date_description',
        component: 'due-date',
        dueDate: {
          autoKey: 'office_invoice_set_due_date_automatically',
          daysKey: 'office_invoice_due_date_days',
        },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.office_invoices.default_formats',
        descriptionKey: 'settings.customization.office_invoices.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'office_invoice_mail_body',
          mailBodyLabelKey: 'settings.customization.office_invoices.default_office_invoice_email_body',
          mailBodyFields: ['customer', 'company', 'office_invoice'],
          addressFormats: [
            { key: 'office_invoice_company_address_format', labelKey: 'settings.customization.office_invoices.company_address_format', customInputFields: ['company'] },
            { key: 'office_invoice_shipping_address_format', labelKey: 'settings.customization.office_invoices.shipping_address_format', customInputFields: ['shipping', 'customer'] },
            { key: 'office_invoice_billing_address_format', labelKey: 'settings.customization.office_invoices.billing_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'retrospective-edits',
        titleKey: 'settings.customization.office_invoices.retrospective_edits',
        descriptionKey: 'settings.customization.office_invoices.retrospective_edits_description',
        component: 'retrospective-edits',
        retrospective: { variant: 'toggle', settingKey: 'office_invoice_allow_edit_after_send' },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'office_invoice', settingKey: 'default_office_invoice_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.office_invoices.office_invoice_email_attachment',
        descriptionKey: 'settings.customization.office_invoices.office_invoice_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'office_invoice_email_attachment' },
      },
    ],
  },


  // ── Tab 4: LR Receipts (Dockets) ──────────
  {
    id: 'lr-receipts',
    titleKey: 'settings.customization.lr_receipts.title',
    enabledByKey: 'enable_lr_receipts',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.lr_receipts.lr_receipt_number_format',
        descriptionKey: 'settings.customization.lr_receipts.lr_receipt_number_format_description',
        component: 'number-customizer',
        numberCustomizer: { type: 'invoice', defaultSeries: 'LR', settingKey: 'lr_receipt_number_format' },
      },
      {
        id: 'due-date',
        titleKey: 'settings.customization.lr_receipts.due_date',
        descriptionKey: 'settings.customization.lr_receipts.due_date_description',
        component: 'due-date',
        dueDate: {
          autoKey: 'lr_receipt_set_due_date_automatically',
          daysKey: 'lr_receipt_due_date_days',
        },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.lr_receipts.default_formats',
        descriptionKey: 'settings.customization.lr_receipts.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'lr_receipt_mail_body',
          mailBodyLabelKey: 'settings.customization.lr_receipts.default_lr_receipt_email_body',
          mailBodyFields: ['customer', 'company', 'lr_receipt'],
          addressFormats: [
            { key: 'lr_receipt_company_address_format', labelKey: 'settings.customization.lr_receipts.company_address_format', customInputFields: ['company'] },
            { key: 'lr_receipt_shipping_address_format', labelKey: 'settings.customization.lr_receipts.shipping_address_format', customInputFields: ['shipping', 'customer'] },
            { key: 'lr_receipt_billing_address_format', labelKey: 'settings.customization.lr_receipts.billing_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'retrospective-edits',
        titleKey: 'settings.customization.lr_receipts.retrospective_edits',
        descriptionKey: 'settings.customization.lr_receipts.retrospective_edits_description',
        component: 'retrospective-edits',
        retrospective: { variant: 'toggle', settingKey: 'lr_receipt_allow_edit_after_send' },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'lr_receipt', settingKey: 'default_lr_receipt_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.lr_receipts.lr_receipt_email_attachment',
        descriptionKey: 'settings.customization.lr_receipts.lr_receipt_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'lr_receipt_email_attachment' },
      },
    ],
  },


  // ── Tab 5: Lorry Receipts (Challans) ──────
  {
    id: 'lorry-receipts',
    titleKey: 'settings.customization.lorry_receipts.title',
    enabledByKey: 'enable_lorry_receipts',
    sections: buildDocumentSections({
      numberCustomizer: { type: 'invoice', defaultSeries: 'CH', settingKey: 'lorry_receipt_number_format' },
      defaultFormats: {
        mailBodyKey: 'lorry_receipt_mail_body',
        mailBodyLabelKey: 'settings.customization.lorry_receipts.default_lorry_receipt_email_body',
        mailBodyFields: ['customer', 'company', 'lorry_receipt'],
        addressFormats: [
          { key: 'lorry_receipt_company_address_format', labelKey: 'settings.customization.lorry_receipts.company_address_format', customInputFields: ['company'] },
          { key: 'lorry_receipt_shipping_address_format', labelKey: 'settings.customization.lorry_receipts.shipping_address_format', customInputFields: ['shipping', 'customer'] },
          { key: 'lorry_receipt_billing_address_format', labelKey: 'settings.customization.lorry_receipts.billing_address_format', customInputFields: ['billing', 'customer'] },
        ],
      },
      retrospective: { variant: 'toggle', settingKey: 'lorry_receipt_allow_edit_after_send' },
      templateUploader: { documentType: 'lorry_receipt', settingKey: 'default_lorry_receipt_template' },
      emailAttachment: { settingKey: 'lorry_receipt_email_attachment' },
    }),
  },

  // ── Tab 6: Estimates ──────────────────────
  {
    id: 'estimates',
    titleKey: 'settings.customization.estimates.title',
    enabledByKey: 'enable_estimate',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.estimates.estimate_number_format',
        descriptionKey: 'settings.customization.estimates.estimate_number_format_description',
        component: 'number-customizer',
        numberCustomizer: { type: 'estimate', defaultSeries: 'EST', settingKey: 'estimate_number_format' },
      },
      {
        id: 'expiry-date',
        titleKey: 'settings.customization.estimates.expiry_date_setting',
        descriptionKey: 'settings.customization.estimates.expiry_date_description',
        component: 'expiry-date',
        expiryDate: {
          autoKey: 'estimate_set_expiry_date_automatically',
          daysKey: 'estimate_expiry_date_days',
        },
      },
      {
        id: 'convert-estimate',
        titleKey: 'settings.customization.estimates.convert_estimate_setting',
        descriptionKey: 'settings.customization.estimates.convert_estimate_description',
        component: 'convert-estimate',
        convertEstimate: {
          settingKey: 'estimate_convert_action',
          enumOptions: [
            { labelKey: 'settings.customization.estimates.no_action', value: 'no_action' },
            { labelKey: 'settings.customization.estimates.delete_estimate', value: 'delete_estimate' },
            { labelKey: 'settings.customization.estimates.mark_estimate_as_accepted', value: 'mark_estimate_as_accepted' },
          ],
        },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.estimates.default_formats',
        descriptionKey: 'settings.customization.estimates.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'estimate_mail_body',
          mailBodyLabelKey: 'settings.customization.estimates.default_estimate_email_body',
          mailBodyFields: ['customer', 'company', 'estimate'],
          addressFormats: [
            { key: 'estimate_company_address_format', labelKey: 'settings.customization.estimates.company_address_format', customInputFields: ['company'] },
            { key: 'estimate_shipping_address_format', labelKey: 'settings.customization.estimates.shipping_address_format', customInputFields: ['shipping', 'customer'] },
            { key: 'estimate_billing_address_format', labelKey: 'settings.customization.estimates.billing_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'estimate1', settingKey: 'default_estimate_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.estimates.estimate_email_attachment',
        descriptionKey: 'settings.customization.estimates.estimate_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'estimate_email_attachment' },
      },
    ],
  },

  // ── Tab 7: Quotations ─────────────────────
  {
    id: 'quotations',
    titleKey: 'settings.customization.quotations.title',
    enabledByKey: 'enable_quotation',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.quotations.quotation_number_format',
        descriptionKey: 'settings.customization.quotations.quotation_number_format_description',
        component: 'number-customizer',
        numberCustomizer: { type: 'quotation', defaultSeries: 'QUO', settingKey: 'quotation_number_format' },
      },
      {
        id: 'expiry-date',
        titleKey: 'settings.customization.quotations.expiry_date_setting',
        descriptionKey: 'settings.customization.quotations.expiry_date_description',
        component: 'expiry-date',
        expiryDate: {
          autoKey: 'quotation_set_expiry_date_automatically',
          daysKey: 'quotation_expiry_date_days',
        },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.quotations.default_formats',
        descriptionKey: 'settings.customization.quotations.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'quotation_mail_body',
          mailBodyLabelKey: 'settings.customization.quotations.default_quotation_email_body',
          mailBodyFields: ['customer', 'company', 'quotation'],
          addressFormats: [
            { key: 'quotation_company_address_format', labelKey: 'settings.customization.quotations.company_address_format', customInputFields: ['company'] },
            { key: 'quotation_shipping_address_format', labelKey: 'settings.customization.quotations.shipping_address_format', customInputFields: ['shipping', 'customer'] },
            { key: 'quotation_billing_address_format', labelKey: 'settings.customization.quotations.billing_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'retrospective-edits',
        titleKey: 'settings.customization.quotations.retrospective_edits',
        descriptionKey: 'settings.customization.quotations.retrospective_edits_description',
        component: 'retrospective-edits',
        retrospective: { variant: 'toggle', settingKey: 'quotation_allow_edit_after_send' },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'quotation1', settingKey: 'default_quotation_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.quotations.quotation_email_attachment',
        descriptionKey: 'settings.customization.quotations.quotation_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'quotation_email_attachment' },
      },
    ],
  },

  // ── Tab 8: Payments ───────────────────────
  {
    id: 'payments',
    titleKey: 'settings.customization.payments.title',
    enabledByKey: 'enable_payments',
    sections: [
      {
        id: 'number-format',
        titleKey: 'settings.customization.payments.payment_number_format',
        descriptionKey: 'settings.customization.payments.payment_number_format_description',
        component: 'number-customizer',
        numberCustomizer: { type: 'payment', defaultSeries: 'PAY', settingKey: 'payment_number_format' },
      },
      {
        id: 'default-formats',
        titleKey: 'settings.customization.payments.default_formats',
        descriptionKey: 'settings.customization.payments.default_formats_description',
        component: 'default-formats',
        defaultFormats: {
          mailBodyKey: 'payment_mail_body',
          mailBodyLabelKey: 'settings.customization.payments.default_payment_email_body',
          mailBodyFields: ['customer', 'company', 'payment'],
          addressFormats: [
            { key: 'payment_company_address_format', labelKey: 'settings.customization.payments.company_address_format', customInputFields: ['company'] },
            { key: 'payment_from_customer_address_format', labelKey: 'settings.customization.payments.from_customer_address_format', customInputFields: ['billing', 'customer'] },
          ],
        },
      },
      {
        id: 'retrospective-edits',
        titleKey: 'settings.customization.payments.retrospective_edits',
        descriptionKey: 'settings.customization.payments.retrospective_edits_description',
        component: 'retrospective-edits',
        retrospective: { variant: 'toggle', settingKey: 'payment_allow_edit_after_send' },
      },
      {
        id: 'pdf-template',
        titleKey: 'settings.customization.custom_template_title',
        descriptionKey: 'settings.customization.custom_template_description',
        component: 'template-uploader',
        templateUploader: { documentType: 'payment', settingKey: 'default_payment_template' },
      },
      {
        id: 'email-attachment',
        titleKey: 'settings.customization.payments.payment_email_attachment',
        descriptionKey: 'settings.customization.payments.payment_email_attachment_setting_description',
        component: 'email-attachment',
        emailAttachment: { settingKey: 'payment_email_attachment' },
      },
    ],
  },

  // ── Tab 9: Items ──────────────────────────
  {
    id: 'items',
    titleKey: 'settings.customization.items.title',
    enabledByKey: 'enable_items',
    special: 'item-units',
    sections: [],
  },
]

// ──────────────────────────────────────────────
// Document Types Tab Config
// ──────────────────────────────────────────────

export interface DocumentTypeToggle {
  key: string
  titleKey: string
  descriptionKey: string
}

export const documentTypeToggles: DocumentTypeToggle[] = [
  { key: 'enable_standard_invoices', titleKey: 'settings.customization.document_types.standard_invoices', descriptionKey: 'settings.customization.document_types.standard_invoices_desc' },
  { key: 'enable_invoice_receipts', titleKey: 'settings.customization.document_types.invoice_receipts', descriptionKey: 'settings.customization.document_types.invoice_receipts_desc' },
  { key: 'enable_estimate', titleKey: 'settings.customization.document_types.estimates', descriptionKey: 'settings.customization.document_types.estimates_desc' },
  { key: 'enable_quotation', titleKey: 'settings.customization.document_types.quotations', descriptionKey: 'settings.customization.document_types.quotations_desc' },
  { key: 'enable_lr_receipts', titleKey: 'settings.customization.document_types.lr_receipts', descriptionKey: 'settings.customization.document_types.lr_receipts_desc' },
  { key: 'enable_lorry_receipts', titleKey: 'settings.customization.document_types.lorry_receipts', descriptionKey: 'settings.customization.document_types.lorry_receipts_desc' },
  { key: 'enable_warehouse_items', titleKey: 'settings.customization.document_types.warehouse_items', descriptionKey: 'settings.customization.document_types.warehouse_items_desc' },
  { key: 'enable_fleet_management', titleKey: 'settings.customization.document_types.fleet_management', descriptionKey: 'settings.customization.document_types.fleet_management_desc' },
  { key: 'enable_customers', titleKey: 'settings.customization.document_types.customers', descriptionKey: 'settings.customization.document_types.customers_desc' },
  { key: 'enable_items', titleKey: 'settings.customization.document_types.items', descriptionKey: 'settings.customization.document_types.items_desc' },
  { key: 'enable_dashboard', titleKey: 'settings.customization.document_types.dashboard', descriptionKey: 'settings.customization.document_types.dashboard_desc' },
  { key: 'enable_payments', titleKey: 'settings.customization.document_types.payments', descriptionKey: 'settings.customization.document_types.payments_desc' },
  { key: 'enable_expenses', titleKey: 'settings.customization.document_types.expenses', descriptionKey: 'settings.customization.document_types.expenses_desc' },
  { key: 'enable_reports', titleKey: 'settings.customization.document_types.reports', descriptionKey: 'settings.customization.document_types.reports_desc' },
  { key: 'enable_members', titleKey: 'settings.customization.document_types.members', descriptionKey: 'settings.customization.document_types.members_desc' },
]
