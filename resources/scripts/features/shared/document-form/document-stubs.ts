import { generateClientId } from '../../../utils'
import type { DocumentTax, DocumentItem } from './use-document-calculations'

/**
 * Shared stub factories for document forms.
 *
 * These were previously duplicated identically in the invoice store and the
 * estimate store.  Centralising them here ensures that any future field additions
 * (e.g. new transport columns) only need to be made in one place.
 */

/**
 * Create a blank tax row stub for document forms.
 */
export function createTaxStub(): DocumentTax {
  return {
    id: generateClientId(),
    name: '',
    tax_type_id: 0,
    type: 'GENERAL',
    amount: null,
    percent: null,
    compound_tax: false,
    calculation_type: null,
    fixed_amount: 0,
  }
}

/**
 * Base document item stub — contains only the fields common to all document
 * types (invoices, estimates).  Document-specific stubs can spread this base
 * and add their own columns.
 */
export function createBaseItemStub(): DocumentItem {
  return {
    id: generateClientId(),
    item_id: null,
    name: '',
    description: null,
    quantity: 1,
    price: 0,
    discount_type: 'fixed',
    discount_val: 0,
    discount: 0,
    total: 0,
    totalTax: 0,
    totalSimpleTax: 0,
    totalCompoundTax: 0,
    tax: 0,
    taxes: [createTaxStub()],
    unit_name: null,
    truck_type: null,
  }
}

/**
 * Create an invoice item stub, including transport-specific fields.
 */
export function createInvoiceItemStub(): DocumentItem {
  return {
    ...createBaseItemStub(),
    invoice_id: null,
    // Office Invoice item-level transport fields (native columns)
    consignment_number: null,
    consignment_date: null,
    party_inv_no: null,
    from_code: null,
    to_code: null,
    truck_no: null,
    pkg: null,
    weight: null,
    rate: null,
    other_charge: null,
    lr_charge: null,
    dd_charge: null,
    amount: null,
  }
}

/**
 * Create an estimate item stub.
 *
 * `rate_card` is a JSON object mapping unit IDs (as string keys) to rate amounts
 * in cents.  e.g. { "1": 1500000, "2": 1600000 } means unit 1 → ₹15,000,
 * unit 2 → ₹16,000.  This powers the Rate Card Matrix in the estimate form
 * where columns are dynamically generated from the `units` table.
 */
export function createEstimateItemStub(): DocumentItem {
  return {
    ...createBaseItemStub(),
    estimate_id: null,
    sub_total: 0,
    rate_card: {} as Record<string, number>,
  }
}
