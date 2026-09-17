import type { Ref } from 'vue'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { TaxType } from '../../../types/domain/tax'
import type { DocumentTax, DocumentItem, DocumentFormData } from './use-document-calculations'
import { createTaxStub } from './document-stubs'

/**
 * Options for the useDocumentActions composable.
 *
 * The `formRef` is a Ref to the reactive form object (e.g. `newInvoice` or
 * `newEstimate`) so that actions can mutate it in place.
 */
export interface UseDocumentActionsOptions<T extends DocumentFormData> {
  /** Reactive ref to the form object */
  formRef: Ref<T>
  /** Factory function to create a new item stub */
  createItemStub: () => DocumentItem
}

/**
 * Shared document form actions that were previously duplicated identically
 * (or near-identically) across the invoice store, estimate store, and payment
 * store.
 *
 * Each store calls `useDocumentActions` with its own form ref and item stub
 * factory, then spreads the returned actions into its Pinia actions.
 *
 * This eliminates ~200 lines of duplicated action implementations.
 */
export function useDocumentActions<T extends DocumentFormData>(
  options: UseDocumentActionsOptions<T>,
) {
  const { formRef, createItemStub } = options

  // ---- Item Management ----

  function addItem(): void {
    formRef.value.items.push(createItemStub())
  }

  function updateItem(data: DocumentItem & { index: number }): void {
    Object.assign(formRef.value.items[data.index], { ...data })
  }

  function removeItem(index: number): void {
    formRef.value.items.splice(index, 1)
  }

  function deselectItem(index: number): void {
    formRef.value.items[index] = createItemStub()
  }

  // ---- Note Management ----

  function selectNote(data: Note): void {
    formRef.value.selectedNote = null
    formRef.value.selectedNote = data
  }

  function resetSelectedNote(): void {
    formRef.value.selectedNote = null
  }

  // ---- Template Management ----

  function setTemplate(name: string): void {
    formRef.value.template_name = name
  }

  // ---- Customer Management ----

  function resetSelectedCustomer(): void {
    formRef.value.customer = null
    formRef.value.customer_id = null
  }

  /**
   * Set customer billing/shipping addresses from the customer's business profile.
   * Shared between invoice and estimate stores.
   */
  function setCustomerAddresses(customer: Customer | null): void {
    if (!customer) return
    const business = (customer as Record<string, unknown>).customer_business as
      | Record<string, unknown>
      | undefined

    if (business?.billing_address) {
      ;(formRef.value.customer as Record<string, unknown>).billing_address =
        business.billing_address
    }
    if (business?.shipping_address) {
      ;(formRef.value.customer as Record<string, unknown>).shipping_address =
        business.shipping_address
    }
  }

  /**
   * Select a customer by ID — fetches from the customer service and populates
   * the form's customer, customer_id, and currency_id fields.
   */
  async function selectCustomer(id: number): Promise<unknown> {
    const { customerService } = await import(
      '../../../api/services/customer.service'
    )
    const response = await customerService.get(id)
    formRef.value.customer = response.data as unknown as Customer
    formRef.value.customer_id = response.data.id
    if (response.data.currency) {
      formRef.value.currency_id = (response.data.currency as { id: number }).id
    }
    return response
  }

  // ---- Tax Helpers ----

  /**
   * Add a "Sales Tax" entry from the form's taxes to the provided taxTypes array.
   * Used by the US sales tax feature in invoice and estimate forms.
   */
  function addSalesTaxUs(taxTypes: TaxType[]): void {
    const salesTax = createTaxStub()
    const found = formRef.value.taxes.find(
      (t) => t.name === 'Sales Tax' && t.type === 'MODULE',
    )
    if (found) {
      for (const key in found) {
        if (Object.prototype.hasOwnProperty.call(salesTax, key)) {
          ;(salesTax as Record<string, unknown>)[key] = (
            found as Record<string, unknown>
          )[key]
        }
      }
      salesTax.id = found.tax_type_id
      taxTypes.push(salesTax as unknown as TaxType)
    }
  }

  // ---- Document Data Hydration ----

  /**
   * Hydrate the form from a fetched document model.
   *
   * Handles the shared logic for:
   *  - Object.assign the document into the form
   *  - Ensuring tax_per_item items have at least one tax stub
   *  - Normalising discount values (fixed → percentage display)
   *
   * This was duplicated identically between invoice and estimate stores.
   */
  function setDocumentData(
    document: Record<string, unknown>,
    opts?: {
      /** Override the key for discount_type on the form (default: 'discount_type') */
      discountTypeKey?: string
    },
  ): void {
    Object.assign(formRef.value, document)

    if (formRef.value.tax_per_item === 'YES') {
      formRef.value.items.forEach((item) => {
        if (item.taxes && !item.taxes.length) {
          item.taxes.push(createTaxStub())
        }
      })
    }

    if (formRef.value.discount_per_item === 'YES') {
      formRef.value.items.forEach((item, index) => {
        if (item.discount_type === 'fixed') {
          formRef.value.items[index].discount = item.discount / 100
        }
      })
    } else {
      if (formRef.value.discount_type === 'fixed') {
        formRef.value.discount = formRef.value.discount / 100
      }
    }
  }

  return {
    // Item management
    addItem,
    updateItem,
    removeItem,
    deselectItem,
    // Note management
    selectNote,
    resetSelectedNote,
    // Template management
    setTemplate,
    // Customer management
    resetSelectedCustomer,
    setCustomerAddresses,
    selectCustomer,
    // Tax helpers
    addSalesTaxUs,
    // Document data hydration
    setDocumentData,
  }
}
