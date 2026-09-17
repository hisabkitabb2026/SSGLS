import { defineStore } from 'pinia'
import { useNotificationStore } from '../../../stores/notification.store'
import { useCompanyStore } from '../../../stores/company.store'
import { useUserStore } from '../../../stores/user.store'
import { quotationService } from '../../../api/services/quotation.service'
import type {
  QuotationListParams,
  QuotationListResponse,
  SendQuotationPayload,
  QuotationStatusPayload,
  QuotationTemplate,
} from '../../../api/services/quotation.service'
import type { Quotation, QuotationItem, DiscountType } from '../../../types/domain/quotation'
import type { Invoice } from '../../../types/domain/invoice'
import type { Tax, TaxType } from '../../../types/domain/tax'
import type { Currency } from '../../../types/domain/currency'
import type { Customer } from '../../../types/domain/customer'
import type { Note } from '../../../types/domain/note'
import type { CustomFieldValue } from '../../../types/domain/custom-field'
import type { DocumentTax, DocumentItem } from '../../shared/document-form/use-document-calculations'
import { generateClientId } from '../../../utils'

// ----------------------------------------------------------------
// Stub factories
// ----------------------------------------------------------------

function createTaxStub(): DocumentTax {
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

function createQuotationItemStub(): DocumentItem {
  return {
    id: generateClientId(),
    estimate_id: null,
    item_id: null,
    name: '',
    description: null,
    quantity: 1,
    price: 0,
    discount_type: 'fixed',
    discount_val: 0,
    discount: 0,
    total: 0,
    sub_total: 0,
    totalTax: 0,
    totalSimpleTax: 0,
    totalCompoundTax: 0,
    tax: 0,
    taxes: [createTaxStub()],
    unit_name: null,
  }
}

export interface QuotationFormData {
  id: number | null
  customer: Customer | null
  template_name: string | null
  tax_per_item: string | null
  tax_included: boolean
  sales_tax_type: string | null
  sales_tax_address_type: string | null
  discount_per_item: string | null
  estimate_date: string
  expiry_date: string
  estimate_number: string
  customer_id: number | null
  sub_total: number
  total: number
  tax: number
  notes: string | null
  discount_type: DiscountType
  discount_val: number
  reference_number: string | null
  discount: number
  items: DocumentItem[]
  taxes: DocumentTax[]
  customFields: CustomFieldValue[]
  fields: CustomFieldValue[]
  selectedNote: Note | null
  selectedCurrency: Currency | Record<string, unknown> | string
  unique_hash?: string
  exchange_rate?: number | null
  currency_id?: number
  quotation_stations?: Array<{
    id?: number
    name: string
    rates: Array<{
      id?: number
      capacity: string
      rate: number
    }>
  }>
}

function createQuotationStub(): QuotationFormData {
  return {
    id: null,
    customer: null,
    template_name: '',
    tax_per_item: null,
    tax_included: false,
    sales_tax_type: null,
    sales_tax_address_type: null,
    discount_per_item: null,
    estimate_date: '',
    expiry_date: '',
    estimate_number: '',
    customer_id: null,
    sub_total: 0,
    total: 0,
    tax: 0,
    notes: '',
    discount_type: 'fixed',
    discount_val: 0,
    reference_number: null,
    discount: 0,
    items: [],
    taxes: [],
    customFields: [],
    fields: [],
    selectedNote: null,
    selectedCurrency: '',
    quotation_stations: [
      {
        name: '',
        rates: [],
      },
    ],
  }
}

// ----------------------------------------------------------------
// Store
// ----------------------------------------------------------------

export interface QuotationState {
  templates: QuotationTemplate[]
  quotations: Quotation[]
  selectAllField: boolean
  selectedQuotations: number[]
  totalQuotationCount: number
  isFetchingInitialSettings: boolean
  showExchangeRate: boolean
  newQuotation: QuotationFormData
}

export const useQuotationStore = defineStore('quotation', {
  state: (): QuotationState => ({
    templates: [],
    estimates: [],
    selectAllField: false,
    selectedQuotations: [],
    totalQuotationCount: 0,
    isFetchingInitialSettings: false,
    showExchangeRate: false,
    newQuotation: createQuotationStub(),
  }),

  getters: {
    getSubTotal(state): number {
      return state.newQuotation.items.reduce(
        (sum: number, item: DocumentItem) => sum + (item.total ?? 0),
        0,
      )
    },

    getNetTotal(): number {
      if (this.newQuotation.tax_included) {
        return this.getSubtotalWithDiscount - this.getTotalSimpleTax
      }

      return this.getSubtotalWithDiscount
    },

    getTotalSimpleTax(state): number {
      if (state.newQuotation.tax_per_item === 'YES') {
        return state.newQuotation.items.reduce((sum: number, item: DocumentItem) => {
          return sum + (item.taxes ?? []).reduce((itemSum, tax) => {
            return tax.compound_tax ? itemSum : itemSum + (tax.amount ?? 0)
          }, 0)
        }, 0)
      }

      return state.newQuotation.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (!tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalCompoundTax(state): number {
      if (state.newQuotation.tax_per_item === 'YES') {
        return state.newQuotation.items.reduce((sum: number, item: DocumentItem) => {
          return sum + (item.taxes ?? []).reduce((itemSum, tax) => {
            return tax.compound_tax ? itemSum + (tax.amount ?? 0) : itemSum
          }, 0)
        }, 0)
      }

      return state.newQuotation.taxes.reduce(
        (sum: number, tax: DocumentTax) => {
          if (tax.compound_tax) return sum + (tax.amount ?? 0)
          return sum
        },
        0,
      )
    },

    getTotalTax(): number {
      return this.getTotalSimpleTax + this.getTotalCompoundTax
    },

    getSubtotalWithDiscount(): number {
      return this.getSubTotal - this.newQuotation.discount_val
    },

    getTotal(): number {
      if (this.newQuotation.tax_included) {
        return this.getSubtotalWithDiscount + this.getTotalCompoundTax
      }
      return this.getSubtotalWithDiscount + this.getTotalTax
    },

    isEdit(state): boolean {
      return !!state.newQuotation.id
    },
  },

  actions: {
    resetCurrentQuotation(): void {
      this.newQuotation = createQuotationStub()
    },

    async previewQuotation(params: { id: number }): Promise<unknown> {
      return quotationService.sendPreview(params.id, params)
    },

    async fetchQuotations(
      params: QuotationListParams & { estimate_number?: string },
    ): Promise<{ data: QuotationListResponse }> {
      const response = await quotationService.list({ ...params, estimate_type: 'quotation' })
      this.quotations = response.data
      this.totalQuotationCount = response.meta.estimate_total_count
      return { data: response }
    },

    async getNextNumber(
      params?: Record<string, unknown>,
      setState = false,
    ): Promise<{ data: { nextNumber: string } }> {
      const response = await quotationService.getNextNumber(params as never)
      if (setState) {
        this.newQuotation.estimate_number = response.nextNumber
      }
      return { data: response }
    },

    async fetchQuotation(id: number): Promise<{ data: { data: Quotation } }> {
      const response = await quotationService.get(id)
      this.setQuotationData(response.data)
      this.setCustomerAddresses(this.newQuotation.customer)
      return { data: response }
    },

    setQuotationData(estimate: Quotation): void {
      Object.assign(this.newQuotation, estimate)

      if (this.newQuotation.tax_per_item === 'YES') {
        this.newQuotation.items.forEach((item) => {
          if (item.taxes && !item.taxes.length) {
            item.taxes.push(createTaxStub())
          }
        })
      }

      if (this.newQuotation.discount_per_item === 'YES') {
        this.newQuotation.items.forEach((item, index) => {
          if (item.discount_type === 'fixed') {
            this.newQuotation.items[index].discount = item.discount / 100
          }
        })
      } else {
        if (this.newQuotation.discount_type === 'fixed') {
          this.newQuotation.discount = this.newQuotation.discount / 100
        }
      }
    },

    setCustomerAddresses(customer: Customer | null): void {
      if (!customer) return
      const business = (customer as Record<string, unknown>).customer_business as
        | Record<string, unknown>
        | undefined

      if (business?.billing_address) {
        ;(this.newQuotation.customer as Record<string, unknown>).billing_address =
          business.billing_address
      }
      if (business?.shipping_address) {
        ;(this.newQuotation.customer as Record<string, unknown>).shipping_address =
          business.shipping_address
      }
    },

    addSalesTaxUs(taxTypes: TaxType[]): void {
      const salesTax = createTaxStub()
      const found = this.newQuotation.taxes.find(
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
    },

    async sendQuotation(data: SendQuotationPayload): Promise<unknown> {
      return quotationService.send(data)
    },

    async addQuotation(data: Record<string, unknown>): Promise<{ data: { data: Quotation } }> {
      const response = await quotationService.create({ ...data, estimate_type: 'quotation' } as never)
      this.quotations = [...this.quotations, response.data]

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'quotations.created_message',
      })

      return { data: response }
    },

    async deleteQuotation(payload: { ids: number[] }): Promise<{ data: { success: boolean } }> {
      const response = await quotationService.delete(payload)
      const id = payload.ids[0]
      const index = this.quotations.findIndex((est) => est.id === id)
      if (index !== -1) {
        this.quotations.splice(index, 1)
      }
      return { data: response }
    },

    async deleteMultipleQuotations(): Promise<{ data: { success: boolean } }> {
      const response = await quotationService.delete({
        ids: this.selectedQuotations,
      })
      this.selectedQuotations.forEach((estId) => {
        const index = this.quotations.findIndex((est) => est.id === estId)
        if (index !== -1) {
          this.quotations.splice(index, 1)
        }
      })
      this.selectedQuotations = []
      return { data: response }
    },

    async updateQuotation(data: Record<string, unknown>): Promise<{ data: { data: Quotation } }> {
      const response = await quotationService.update(data.id as number, { ...data, estimate_type: 'quotation' } as never)
      const pos = this.quotations.findIndex((est) => est.id === response.data.id)
      if (pos !== -1) {
        this.quotations[pos] = response.data
      }

      const notificationStore = useNotificationStore()
      notificationStore.showNotification({
        type: 'success',
        message: 'quotations.updated_message',
      })

      return { data: response }
    },

    async cloneQuotation(data: { id: number }): Promise<{ data: { data: Quotation } }> {
      const response = await quotationService.clone(data.id)
      return { data: response }
    },

    async markAsAccepted(data: QuotationStatusPayload): Promise<unknown> {
      const response = await quotationService.changeStatus({
        ...data,
        status: 'ACCEPTED',
      })
      const pos = this.quotations.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.quotations[pos]) {
        this.quotations[pos].status = 'ACCEPTED' as Quotation['status']
      }
      return response
    },

    async markAsRejected(data: QuotationStatusPayload): Promise<unknown> {
      const response = await quotationService.changeStatus({
        ...data,
        status: 'REJECTED',
      })
      return response
    },

    async markAsSent(data: QuotationStatusPayload): Promise<unknown> {
      const response = await quotationService.changeStatus(data)
      const pos = this.quotations.findIndex((est) => est.id === data.id)
      if (pos !== -1 && this.quotations[pos]) {
        this.quotations[pos].status = 'SENT' as Quotation['status']
      }
      return response
    },

    async convertToInvoice(id: number): Promise<{ data: { data: Invoice } }> {
      const response = await quotationService.convertToInvoice(id)
      return { data: response }
    },

    async searchQuotation(queryString: string): Promise<unknown> {
      return quotationService.list(
        Object.fromEntries(new URLSearchParams(queryString)) as never,
      )
    },

    selectQuotation(data: number[]): void {
      this.selectedQuotations = data
      this.selectAllField =
        this.selectedQuotations.length === this.quotations.length
    },

    selectAllQuotations(): void {
      if (this.selectedQuotations.length === this.quotations.length) {
        this.selectedQuotations = []
        this.selectAllField = false
      } else {
        this.selectedQuotations = this.quotations.map((est) => est.id)
        this.selectAllField = true
      }
    },

    async selectCustomer(id: number): Promise<unknown> {
      const { customerService } = await import(
        '../../../api/services/customer.service'
      )
      const response = await customerService.get(id)
      this.newQuotation.customer = response.data as unknown as Customer
      this.newQuotation.customer_id = response.data.id
      if (response.data.currency) {
        this.newQuotation.currency_id = (response.data.currency as { id: number }).id
      }
      return response
    },

    async fetchQuotationTemplates(): Promise<{
      data: { estimateTemplates: QuotationTemplate[] }
    }> {
      const response = await quotationService.getTemplates()
      this.templates = response.estimateTemplates
      return { data: response }
    },

    setTemplate(name: string): void {
      this.newQuotation.template_name = name
    },

    resetSelectedCustomer(): void {
      this.newQuotation.customer = null
      this.newQuotation.customer_id = null
    },

    selectNote(data: Note): void {
      this.newQuotation.selectedNote = null
      this.newQuotation.selectedNote = data
    },

    resetSelectedNote(): void {
      this.newQuotation.selectedNote = null
    },

    addItem(): void {
      this.newQuotation.items.push(createQuotationItemStub())
    },

    updateItem(data: DocumentItem & { index: number }): void {
      Object.assign(this.newQuotation.items[data.index], { ...data })
    },

    removeItem(index: number): void {
      this.newQuotation.items.splice(index, 1)
    },

    deselectItem(index: number): void {
      this.newQuotation.items[index] = createQuotationItemStub()
    },

    async fetchQuotationInitialSettings(
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
        this.newQuotation.selectedCurrency = companyCurrency ?? companyStore.selectedCompanyCurrency!
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
        this.newQuotation.tax_per_item = companySettings.tax_per_item ?? null
        this.newQuotation.tax_included =
          companySettings.tax_included === 'YES' &&
          companySettings.tax_included_by_default === 'YES'
        this.newQuotation.sales_tax_type = companySettings.sales_tax_type ?? null
        this.newQuotation.sales_tax_address_type =
          companySettings.sales_tax_address_type ?? null
        this.newQuotation.discount_per_item =
          companySettings.discount_per_item ?? null

        const now = new Date()
        this.newQuotation.estimate_date = formatDate(now, 'YYYY-MM-DD')

        if (companySettings.estimate_set_expiry_date_automatically === 'YES') {
          const expiryDate = new Date(now)
          expiryDate.setDate(
            expiryDate.getDate() +
              Number(companySettings.estimate_expiry_date_days ?? 7),
          )
          this.newQuotation.expiry_date = formatDate(expiryDate, 'YYYY-MM-DD')
        }
      } else if (isEdit && routeParams?.id) {
        editActions.push(this.fetchQuotation(Number(routeParams.id)))
      }

      try {
        const [, , templatesRes, nextNumRes] = await Promise.all([
          Promise.resolve(), // placeholder for items fetch
          this.resetSelectedNote(),
          this.fetchQuotationTemplates(),
          this.getNextNumber(),
          Promise.resolve(), // placeholder for tax types fetch
          ...editActions,
        ])

        if (!isEdit) {
          if (nextNumRes?.data?.nextNumber) {
            this.newQuotation.estimate_number = nextNumRes.data.nextNumber
          }

          if (this.templates.length) {
            this.setTemplate(this.templates[0].name)
            const { currentUserSettings } = useUserStore()
            if (currentUserSettings.default_estimate_template) {
              this.newQuotation.template_name =
                currentUserSettings.default_estimate_template
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

/** Simple date formatter without moment dependency */
function formatDate(date: Date, _format: string): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

export type QuotationStore = ReturnType<typeof useQuotationStore>
