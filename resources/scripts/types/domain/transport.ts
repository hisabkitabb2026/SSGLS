/**
 * Transport & Lorry Receipt Type Definitions
 *
 * Enums and interfaces for transport templates (lr_receipt, lorry_receipt, office_invoice)
 */

export enum TransportTemplateType {
  LR_RECEIPT = 'lr_receipt',
  LORRY_RECEIPT = 'lorry_receipt',
  OFFICE_INVOICE = 'office_invoice',
}

/**
 * LR Receipt (Light Goods Receipt) specific fields
 */
export interface LRReceiptFields {
  // Trip Details
  from_code?: string
  from_name?: string
  to_code?: string
  to_name?: string
  truck_no?: string
  mode_of_payment?: string
  gst_tax_payable_by?: string

  // Consignment Details
  description_of_goods?: string
  hsn_code?: string
  eway_bill_no?: string
  actual_weight?: string
  charged_weight?: string
  no_of_articles?: string
  packing?: string

  // Freight Details (in cents as bigInteger)
  basic_freight?: number
  hamali?: number
  fov?: number
  local_collection?: number
  door_delivery?: number
  docket_charge?: number
  other_charge?: number
  net_amount?: number
}

/**
 * Lorry Receipt specific fields
 */
export interface LorryReceiptFields {
  // Trip Details
  no_of_pages?: string
  no_of_packages?: string
  distance_kms?: string
  rate?: string
  regd_at?: string

  // Vehicle Details
  body_type?: string
  make?: string
  vehicle_model?: string
  colour?: string
  chasis_no?: string
  engine_no?: string

  // Fitness & Permit
  fitness_validity?: string
  road_permit_no?: string
  permit_date?: string
  permit_valid_in?: string
  permit_status_upto?: string

  // Insurance
  insured_with?: string
  insurance_division_no?: string
  insurance_certificate_no?: string
  insurance_valid_upto?: string

  // Owner Details
  owner_code?: string
  owner_name?: string
  owner_address?: string
  owner_phone?: string
  owner_bank_account_no?: string
  owner_pan_no?: string
  owner_customer_id?: number

  // Financer Details
  financer_name?: string
  financer_address?: string

  // Driver Details
  driver_name?: string
  driver_address?: string
  driver_place?: string
  driver_licence_no?: string
  driver_licence_date?: string
  driver_licence_issued_by?: string
  driver_rto_address?: string
  driver_valid_up_to?: string
  driver_bank_account_no?: string
  driver_customer_id?: number

  // Broker Details
  broker_name?: string
  broker_address?: string
  broker_pan_no?: string
  broker_phone_no?: string
  broker_bank_account_no?: string
  broker_customer_id?: number

  // Advice
  advice_no?: string
  advice_date?: string

  // Destination Broker
  destination_broker_name?: string
  destination_broker_address?: string

  // Hire & Payment - Initial
  paid_to?: string
  lorry_hire_amount?: string
  other_charges_amount?: string
  gross_hire_rupees?: string
  gross_hire_amount?: string

  // Advance
  advance_cash_cheque_no?: string
  advance_on?: string
  advance_bank?: string
  advance_amount?: string

  // Balance
  balance_payable_at?: string
  balance_payable_code?: string
  balance_rupees?: string
  balance_amount?: string
  balance_rupees_only?: string

  // Authorization - Initial
  hire_passed_by?: string
  hire_certified_by?: string
  hire_prepared_by?: string
  advance_received_by?: string

  // Loading
  loading_remarks?: string
  loaded_by?: string

  // Final Settlement
  final_paid_to?: string
  detention_amount?: string
  extra_hire_amount?: string
  final_other_amount?: string
  final_total_extra_amount?: string
  grand_total_amount?: string
  less_advance_other_branch_amount?: string
  less_deduction_claims_amount?: string
  total_less_amount?: string

  // Final Balance
  final_balance_paid_at?: string
  final_balance_code?: string
  final_balance_on?: string
  net_amount_payable?: string
  final_cash_cheque_no?: string
  final_cash_cheque_on?: string
  final_bank?: string
  final_rupees_only?: string

  // Final Authorization
  final_passed_by?: string
  final_certified_by?: string
  final_prepared_by?: string
  final_payment_received_by?: string

  // Bilties & Contract
  received_no_bilties?: string
  contract_no?: string
}

/**
 * Office Invoice specific fields
 */
export interface OfficeInvoiceFields {
  // Fields specific to office invoices (if any)
  // Most fields are shared with LorryReceipt
}

/**
 * Union type for all transport fields
 */
export type TransportFields = LRReceiptFields & LorryReceiptFields & OfficeInvoiceFields

/**
 * Transport template response structure
 */
export interface TransportResponse {
  data: Partial<TransportFields>
  meta?: unknown
}

/**
 * Transport document create/update request payload
 */
export interface TransportDocumentPayload {
  template_name: TransportTemplateType
  transport_fields?: Partial<TransportFields>
  items?: Array<Record<string, unknown>>
  taxes?: Array<Record<string, unknown>>
  customFields?: Array<{
    id: number
    value: string
  }>
}

/**
 * Helper type guard to check if a template is a transport template
 */
export function isTransportTemplate(templateName?: string): templateName is TransportTemplateType {
  return Object.values(TransportTemplateType).includes(templateName as TransportTemplateType)
}

/**
 * Helper to get template display name
 */
export function getTransportTemplateLabel(templateName: TransportTemplateType): string {
  const labels: Record<TransportTemplateType, string> = {
    [TransportTemplateType.LR_RECEIPT]: 'LR Receipt',
    [TransportTemplateType.LORRY_RECEIPT]: 'Lorry Receipt',
    [TransportTemplateType.OFFICE_INVOICE]: 'Office Invoice',
  }
  return labels[templateName] || 'Unknown'
}
