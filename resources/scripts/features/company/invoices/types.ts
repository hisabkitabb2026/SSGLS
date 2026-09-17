import type { DiscountType } from '../../../types/domain/invoice'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type { DocumentTax, DocumentItem } from '../../shared/document-form/use-document-calculations'

/**
 * Base invoice form data — fields common to ALL invoice templates
 * (standard invoice1, invoice2, etc.).
 *
 * Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
 * extend this with additional transport-specific fields.
 */
export interface BaseInvoiceFormData {
  id: number | null
  invoice_number: string
  customer: Customer | null
  customer_id: number | null
  consignee_customer_id: number | null
  consignee_customer: Customer | null
  template_name: string | null
  invoice_date: string
  due_date: string
  notes: string | null
  discount: number
  discount_type: DiscountType
  discount_val: number
  reference_number: string | null
  tax: number
  sub_total: number
  total: number
  tax_per_item: string | null
  tax_included: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  discount_per_item: string | null
  taxes: DocumentTax[]
  items: DocumentItem[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  selectedNote: Note | null
  selectedCurrency: Currency | Record<string, unknown> | string
  unique_hash?: string
  exchange_rate?: number | null
  currency_id?: number
}

/**
 * Transport receipt form data — extends the base with all transport-specific
 * fields used by lr_receipt, lorry_receipt, and office_invoice templates.
 *
 * These fields are stored as native columns on the invoices table and are
 * only relevant when the template_name is one of the transport templates.
 */
export interface TransportInvoiceFormData extends BaseInvoiceFormData {
  // Transport route fields
  from_code?: string | null
  to_code?: string | null
  truck_no?: string | null
  mode_of_payment?: string | null
  gst_tax_payable_by?: string | null
  description_of_goods?: string | null
  hsn_code?: string | null
  eway_bill_no?: string | null

  // Weight & package fields
  actual_weight?: string | null
  charged_weight?: string | null
  no_of_articles?: string | null
  packing?: string | null
  no_of_pages?: string | null
  no_of_packages?: string | null

  // Freight & charges
  basic_freight?: string | null
  hamali?: string | null
  fov?: string | null
  local_collection?: string | null
  door_delivery?: string | null
  docket_charge?: string | null
  other_charge?: string | null
  net_amount?: string | null

  // Vehicle details
  regd_at?: string | null
  body_type?: string | null
  make?: string | null
  vehicle_model?: string | null
  colour?: string | null
  chasis_no?: string | null
  engine_no?: string | null

  // Lorry hire & advance (Section C)
  paid_to?: string | null
  lorry_hire_amount?: string | null
  other_charges_amount?: string | null
  advance_cash_cheque_no?: string | null
  advance_on?: string | null
  advance_bank?: string | null
  advance_amount?: string | null
  balance_payable_at?: string | null
  loaded_by?: string | null

  // Final settlement (Section E)
  final_paid_to?: string | null
  detention_amount?: string | null
  extra_hire_amount?: string | null
  final_other_amount?: string | null
  less_advance_other_branch_amount?: string | null
  less_deduction_claims_amount?: string | null
  final_balance_paid_at?: string | null
  final_balance_on?: string | null
  final_cash_cheque_no?: string | null
  final_bank?: string | null

  // Owner details
  owner_name?: string | null
  owner_address?: string | null
  owner_phone?: string | null
  owner_bank_account_no?: string | null
  owner_pan_no?: string | null
  financer_address?: string | null

  // Driver details
  driver_name?: string | null
  driver_address?: string | null
  driver_place?: string | null
  driver_licence_no?: string | null
  driver_licence_date?: string | null
  driver_licence_issued_by?: string | null
  driver_rto_address?: string | null
  driver_valid_up_to?: string | null
  driver_bank_account_no?: string | null

  // Broker details
  broker_name?: string | null
  broker_address?: string | null
  broker_pan_no?: string | null
  broker_phone_no?: string | null
  broker_bank_account_no?: string | null
  advice_date?: string | null
  destination_broker_name?: string | null
  destination_broker_address?: string | null

  // Lorry Receipt computed totals
  gross_hire_rupees?: string | null
  net_amount_payable?: string | null
  received_no_bilties?: string | null
  contract_no?: string | null

  // Party profile reference (LorryPartyProfile ID for the "Party" field
  // on Lorry Receipts — replaces the Customer-based Party selector)
  party_profile_id?: number | null
}


/**
 * Discriminated union of all invoice form data types.
 *
 * The `template_name` field acts as the discriminant:
 *  - 'lr_receipt', 'lorry_receipt', 'office_invoice' → TransportInvoiceFormData
 *  - everything else (invoice1, invoice2, etc.) → BaseInvoiceFormData
 *
 * Usage:
 *   function processInvoice(data: InvoiceFormData) {
 *     if (isTransportInvoice(data)) {
 *       // data is now typed as TransportInvoiceFormData
 *       data.truck_no // ✓ accessible
 *     } else {
 *       // data is typed as BaseInvoiceFormData
 *       data.truck_no // ✗ type error
 *     }
 *   }
 */
export type InvoiceFormData = BaseInvoiceFormData | TransportInvoiceFormData

/**
 * Type guard: check if the form data is for a transport receipt template.
 */
export function isTransportInvoice(data: InvoiceFormData): data is TransportInvoiceFormData {
  return (
    data.template_name === 'lr_receipt' ||
    data.template_name === 'lorry_receipt' ||
    data.template_name === 'office_invoice'
  )
}

/**
 * Transport template names.
 */
export const TRANSPORT_TEMPLATE_NAMES = ['lr_receipt', 'lorry_receipt', 'office_invoice'] as const

export type TransportTemplateName = (typeof TRANSPORT_TEMPLATE_NAMES)[number]
