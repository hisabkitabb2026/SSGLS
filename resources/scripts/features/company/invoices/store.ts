import { defineStore } from 'pinia'
import { toRef } from 'vue'
import { useNotificationStore } from '../../../stores/notification.store'
import { useCompanyStore } from '../../../stores/company.store'
import { useUserStore } from '../../../stores/user.store'
import { invoiceService } from '../../../api/services/invoice.service'
import type {
  InvoiceListParams,
  InvoiceListResponse,
  SendInvoicePayload,
  InvoiceStatusPayload,
  InvoiceTemplate,
} from '../../../api/services/invoice.service'
import type { Invoice } from '../../../types/domain/invoice'
import type { DiscountType } from '../../../types/domain/invoice'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type { DocumentTax, DocumentItem } from '../../shared/document-form/use-document-calculations'
import { useDocumentCalculations } from '../../shared/document-form/use-document-calculations'
import { createInvoiceItemStub, createTaxStub } from '../../shared/document-form/document-stubs'
import { useDocumentActions } from '../../shared/document-form/use-document-actions'
import { formatDate as formatDateUtil } from '../../../utils'
import type { InvoiceFormData } from './types'
import { isTransportInvoice } from './types'

// Re-export for backward compatibility — components that import InvoiceFormData
// from the store will still work.
export type { InvoiceFormData, BaseInvoiceFormData, TransportInvoiceFormData } from './types'
export { isTransportInvoice, TRANSPORT_TEMPLATE_NAMES } from './types'


function createInvoiceStub(): InvoiceFormData {
  return {
    id: null,
    invoice_number: '',
    customer: null,
    customer_id: null,
    consignee_customer_id: null,
    consignee_customer: null,
    template_name: null,
    invoice_date: '',
    due_date: '',
    notes: '',
    discount: 0,
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    tax: 0,
    sub_total: 0,
    total: 0,
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    taxes: [],
    items: [createInvoiceItemStub()],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',

    // ------------------------------------------------------------------
    // Transport receipt native properties (lr_receipt, lorry_receipt,
    // office_invoice templates).
    //
    // These MUST be declared here with null defaults so that Vue's
    // reactivity proxy tracks them from initialization.  If they are
    // absent from the initial object, adding them later (e.g. when a
    // user selects an Owner/Driver/Broker from the dropdown in
    // LorryReceiptPartyFields.vue) creates non-reactive properties —
    // the v-model bindings in TransportCustomFields.vue (Owner Details,
    // Driver Details, Broker Details sections) would not update.
    // ------------------------------------------------------------------

    // Transport route fields
    from_code: null,
    to_code: null,
    truck_no: null,
    mode_of_payment: null,
    gst_tax_payable_by: null,
    description_of_goods: null,
    hsn_code: null,
    eway_bill_no: null,

    // Weight & package fields
    actual_weight: null,
    charged_weight: null,
    no_of_articles: null,
    packing: null,
    no_of_pages: null,
    no_of_packages: null,

    // Freight & charges
    basic_freight: null,
    hamali: null,
    fov: null,
    local_collection: null,
    door_delivery: null,
    docket_charge: null,
    other_charge: null,
    net_amount: null,

    // Vehicle details
    regd_at: null,
    body_type: null,
    make: null,
    vehicle_model: null,
    colour: null,
    chasis_no: null,
    engine_no: null,

    // Lorry hire & advance
    paid_to: null,
    lorry_hire_amount: null,
    other_charges_amount: null,
    advance_cash_cheque_no: null,
    advance_on: null,
    advance_bank: null,
    advance_amount: null,
    balance_payable_at: null,
    loaded_by: null,

    // Final settlement
    final_paid_to: null,
    detention_amount: null,
    extra_hire_amount: null,
    final_other_amount: null,
    less_advance_other_branch_amount: null,
    less_deduction_claims_amount: null,
    final_balance_paid_at: null,
    final_balance_on: null,
    final_cash_cheque_no: null,
    final_bank: null,

    // Owner details
    owner_name: null,
    owner_address: null,
    owner_phone: null,
    owner_bank_account_no: null,
    owner_pan_no: null,
    financer_address: null,

    // Driver details
    driver_name: null,
    driver_address: null,
    driver_place: null,
    driver_licence_no: null,
    driver_licence_date: null,
    driver_licence_issued_by: null,
    driver_rto_address: null,
    driver_valid_up_to: null,
    driver_bank_account_no: null,

    // Broker details
    broker_name: null,
    broker_address: null,
    broker_pan_no: null,
    broker_phone_no: null,
    broker_bank_account_no: null,
    advice_date: null,
    destination_broker_name: null,
    destination_broker_address: null,

    // Lorry Receipt computed totals
    gross_hire_rupees: null,
    net_amount_payable: null,
    received_no_bilties: null,
    contract_no: null,

    // Party profile reference (LorryPartyProfile ID for the "Party" field
    // on Lorry Receipts — replaces the Customer-based Party selector)
    party_profile_id: null,
  } as InvoiceFormData

}


// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface InvoiceState {
  templates: InvoiceTemplate[]
  invoices: Invoice[]
  selectedInvoices: number[]
  selectAllField: boolean
  invoiceTotalCount: number
  showExchangeRate: boolean
  isFetchingInitialSettings: boolean
  isFetchingInvoice: boolean
  newInvoice: InvoiceFormData
}

export const useInvoiceStore = defineStore('invoice', {
  state: (): InvoiceState => ({
    templates: [],
    invoices: [],
    selectedInvoices: [],
    selectAllField: false,
    invoiceTotalCount: 0,
    showExchangeRate: false,
    isFetchingInitialSettings: false,
    isFetchingInvoice: false,
    newInvoice: createInvoiceStub(),
  }),

  getters: {
    getInvoice:
      (state) =>
      (id: number): Invoice | undefined => {
        return state.invoices.find((invoice) => invoice.id === id)
      },

    isEdit(state): boolean {
      return !!state.newInvoice.id
    },

    // Tax calculations delegated to the shared useDocumentCalculations composable
    // — eliminates ~60 lines of duplicated getter logic that was identical to
    // the estimate store.
    getSubTotal(state): number {
      const { subTotal } = useDocumentCalculations({
        items: toRef(state.newInvoice, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newInvoice, 'taxes'),
        discountVal: toRef(state.newInvoice, 'discount_val'),
        taxPerItem: toRef(state.newInvoice, 'tax_per_item'),
        taxIncluded: toRef(state.newInvoice, 'tax_included'),
      })
      return subTotal.value
    },

    getTotalSimpleTax(state): number {
      const { totalSimpleTax } = useDocumentCalculations({
        items: toRef(state.newInvoice, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newInvoice, 'taxes'),
        discountVal: toRef(state.newInvoice, 'discount_val'),
        taxPerItem: toRef(state.newInvoice, 'tax_per_item'),
        taxIncluded: toRef(state.newInvoice, 'tax_included'),
      })
      return totalSimpleTax.value
    },

    getTotalCompoundTax(state): number {
      const { totalCompoundTax } = useDocumentCalculations({
        items: toRef(state.newInvoice, 'items') as unknown as import('vue').Ref<DocumentItem[]>,
        taxes: toRef(state.newInvoice, 'taxes'),
        discountVal: toRef(state.newInvoice, 'discount_val'),
        taxPerItem: toRef(state.newInvoice, 'tax_per_item'),
        taxIncluded: toRef(state.newInvoice, 'tax_included'),
      })
      return totalCompoundTax.value
    },

    getTotalTax(): number {
      return this.getTotalSimpleTax + this.getTotalCompoundTax
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newInvoice.discount_val
    },

    getNetTotal(): number {
      return this.getSubtotalWithDiscount - this.getTotalTax
    },

    getTotal(): number {
      if (this.newInvoice.tax_included) {
        return this.getSubtotalWithDiscount
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },
  },

  actions: {
    // Initialise shared document actions (item management, note management,
    // customer management, tax helpers, document data hydration).
    // These are spread into the store actions below via the setupActions helper.
    _getDocumentActions() {
      return useDocumentActions({
        formRef: toRef(this, 'newInvoice') as unknown as import('vue').Ref<InvoiceFormData & import('../../shared/document-form/use-document-calculations').DocumentFormData>,
        createItemStub: createInvoiceItemStub,
      })
    },

    resetCurrentInvoice(): void {
      this.newInvoice = createInvoiceStub()
    },

    async previewInvoice(params: SendInvoicePayload): Promise<unknown> {
      return invoiceService.sendPreview(params)
    },

    async fetchInvoices(
      params: InvoiceListParams & { invoice_number?: string },
    ): Promise<{ data: InvoiceListResponse }> {
      const response = await invoiceService.list(params)
      this.invoices = response.data
      this.invoiceTotalCount = response.meta.invoice_total_count
      return { data: response }
    },

    async fetchInvoice(id: number): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.get(id)
      this._getDocumentActions().setDocumentData(response.data)
      this._getDocumentActions().setCustomerAddresses(this.newInvoice.customer)
      return { data: response }
    },

    // Delegate to shared actions
    setInvoiceData(invoice: Invoice): void {
      this._getDocumentActions().setDocumentData(invoice)
    },

    setCustomerAddresses(customer: Customer | null): void {
      this._getDocumentActions().setCustomerAddresses(customer)
    },

    addSalesTaxUs(taxTypes: import('../../../types/domain/tax').TaxType[]): void {
      this._getDocumentActions().addSalesTaxUs(taxTypes)
    },

    async sendInvoice(data: SendInvoicePayload): Promise<unknown> {
      const response = await invoiceService.send(data)
      return response
    },

    async addInvoice(data: Record<string, unknown>): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.create(data as never)
      this.invoices = [...this.invoices, response.data]

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'invoices.created_message',
      })

      return { data: response }
    },

    async deleteInvoice(payload: { ids: number[] }): Promise<{ data: { success: boolean } }> {
      const response = await invoiceService.delete(payload)
      const id = payload.ids[0]
      const index = this.invoices.findIndex((inv) => inv.id === id)
      if (index !== -1) {
        this.invoices.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleInvoices(): Promise<{ data: { success: boolean } }> {
      const response = await invoiceService.delete({ ids: this.selectedInvoices })
      this.selectedInvoices.forEach((invoiceId) => {
        const index = this.invoices.findIndex((inv) => inv.id === invoiceId)
        if (index !== -1) {
          this.invoices.splice(index, 1)
        }
      })
      this.selectedInvoices = []
      return { data: response }
    },

    async updateInvoice(data: Record<string, unknown>): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.update(data.id as number, data as never)
      const pos = this.invoices.findIndex((inv) => inv.id === response.data.id)
      if (pos !== -1) {
        this.invoices[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'invoices.updated_message',
      })

      return { data: response }
    },

    async cloneInvoice(data: { id: number }): Promise<{ data: { data: Invoice } }> {
      const response = await invoiceService.clone(data.id)
      return { data: response }
    },

    async convertToEstimate(data: { id: number }): Promise<{ data: { data: Record<string, unknown> } }> {
      const response = await invoiceService.convertToEstimate(data.id)
      return { data: response }
    },

    async markAsSent(data: InvoiceStatusPayload): Promise<unknown> {
      const response = await invoiceService.changeStatus(data)
      const pos = this.invoices.findIndex((inv) => inv.id === data.id)
      if (pos !== -1 && this.invoices[pos]) {
        this.invoices[pos].status = 'SENT' as Invoice['status']
      }
      return response
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await invoiceService.getNextNumber(params as never)
      if (setState) {
        this.newInvoice.invoice_number = response.nextNumber
      }
      return { data: response }
    },

    async searchInvoice(queryString: string): Promise<unknown> {
      return invoiceService.list(
        Object.fromEntries(new URLSearchParams(queryString)) as never,
      )
    },

    selectInvoice(data: number[]): void {
      this.selectedInvoices = data
      this.selectAllField =
        this.selectedInvoices.length === this.invoices.length
    },

    selectAllInvoices(): void {
      if (this.selectedInvoices.length === this.invoices.length) {
        this.selectedInvoices = []
        this.selectAllField = false
      } else {
        this.selectedInvoices = this.invoices.map((inv) => inv.id)
        this.selectAllField = true
      }
    },

    // Delegate item/note/customer/template actions to shared composable
    async selectCustomer(id: number): Promise<unknown> {
      return this._getDocumentActions().selectCustomer(id)
    },

    selectNote(data: Note): void {
      this._getDocumentActions().selectNote(data)
    },

    setTemplate(name: string): void {
      this._getDocumentActions().setTemplate(name)
    },

    resetSelectedCustomer(): void {
      this._getDocumentActions().resetSelectedCustomer()
    },

    addItem(): void {
      this._getDocumentActions().addItem()
    },

    updateItem(data: DocumentItem & { index: number }): void {
      this._getDocumentActions().updateItem(data)
    },

    removeItem(index: number): void {
      this._getDocumentActions().removeItem(index)
    },

    deselectItem(index: number): void {
      this._getDocumentActions().deselectItem(index)
    },

    resetSelectedNote(): void {
      this._getDocumentActions().resetSelectedNote()
    },

    async fetchInvoiceTemplates(
      documentType?: string,
    ): Promise<{ data: { invoiceTemplates: InvoiceTemplate[] } }> {
      const response = await invoiceService.getTemplates(documentType)
      this.templates = response.invoiceTemplates
      return { data: response }
    },

    async fetchInvoiceInitialSettings(
      isEdit: boolean,
      routeParams?: { id?: string; query?: Record<string, string> },
      companySettingsParam?: Record<string, string>,
      companyCurrency?: Currency,
      userSettings?: Record<string, string>,
    ): Promise<void> {
      this.isFetchingInitialSettings = true

      const companyStore = useCompanyStore()
      const companySettings = companySettingsParam ?? companyStore.selectedCompanySettings

      if (companyCurrency || companyStore.selectedCompanyCurrency) {
        this.newInvoice.selectedCurrency = companyCurrency ?? companyStore.selectedCompanyCurrency!
      }

      // If customer is specified in route query
      if (routeParams?.query?.customer) {
        try {
          await this.selectCustomer(Number(routeParams.query.customer))
        } catch {
          // Silently fail
        }
      }

      const editActions: Promise<unknown>[] = []

      if (!isEdit && companySettings) {
        this.newInvoice.tax_per_item = companySettings.tax_per_item ?? null
        this.newInvoice.sales_tax_type = companySettings.sales_tax_type ?? null
        this.newInvoice.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newInvoice.discount_per_item =
          companySettings.discount_per_item ?? null

        let dateFormat = 'yyyy-MM-dd'
        if (companySettings.invoice_use_time === 'YES') {
          dateFormat += ' HH:mm'
        }

        const now = new Date()
        this.newInvoice.invoice_date = formatDateUtil(now, dateFormat)

        if (companySettings.invoice_set_due_date_automatically === 'YES') {
          const dueDate = new Date(now)
          dueDate.setDate(
            dueDate.getDate() +
              Number(companySettings.invoice_due_date_days ?? 7),
          )
          this.newInvoice.due_date = formatDateUtil(dueDate, 'yyyy-MM-dd')
        }
      } else if (isEdit && routeParams?.id) {
        editActions.push(this.fetchInvoice(Number(routeParams.id)))
      }

      // For transport receipt templates, fetch only templates matching the
      // document type so the template selector shows relevant templates.
      const transportDocumentType = this.newInvoice.template_name ?? undefined

      try {
        const [, , templatesRes, nextNumRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          this.fetchInvoiceTemplates(transportDocumentType),
          this.getNextNumber(transportDocumentType ? { template_name: transportDocumentType } : undefined),
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (nextNumRes?.data?.nextNumber) {
            this.newInvoice.invoice_number = nextNumRes.data.nextNumber
          }

          if (templatesRes?.data?.invoiceTemplates?.length) {
            // For transport receipts, the template_name is already set from
            // the route — don't override it with the first template.
            if (!transportDocumentType) {
              this.setTemplate(this.templates[0].name)
              const companyStore = useCompanyStore()
              if (companyStore.selectedCompanySettings.default_invoice_template) {
                this.newInvoice.template_name =
                  companyStore.selectedCompanySettings.default_invoice_template
              } else {
                const { currentUserSettings } = useUserStore()
                if (currentUserSettings.default_invoice_template) {
                  this.newInvoice.template_name =
                    currentUserSettings.default_invoice_template
                }
              }
            }
          }
        }
      } catch {
        // Error handling
      } finally {
        this.isFetchingInitialSettings = false
      }
    },
  },
})

export type InvoiceStore = ReturnType<typeof useInvoiceStore>
