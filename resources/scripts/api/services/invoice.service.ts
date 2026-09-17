import { client } from '../client'
import { API } from '../endpoints'
import { createCrudService, type SendPayload, type StatusPayload } from './crud-service.factory'
import type { Invoice, CreateInvoicePayload } from '@/scripts/types/domain/invoice'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
} from '@/scripts/types/api'

export interface InvoiceListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface InvoiceListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  invoice_total_count: number
}

export interface InvoiceListResponse {
  data: Invoice[]
  meta: InvoiceListMeta
}

/** @deprecated Use SendPayload from crud-service.factory.ts */
export type SendInvoicePayload = SendPayload

/** @deprecated Use StatusPayload from crud-service.factory.ts */
export type InvoiceStatusPayload = StatusPayload

/** @deprecated Use SendPreviewPayload from crud-service.factory.ts */
export type SendPreviewParams = import('./crud-service.factory').SendPreviewPayload

export interface InvoiceTemplate {
  name: string
  path: string
}

export interface InvoiceTemplatesResponse {
  invoiceTemplates: InvoiceTemplate[]
}

// Use the factory for standard CRUD + send/clone/changeStatus methods
const baseInvoiceService = createCrudService<Invoice, InvoiceListParams, CreateInvoicePayload>({
  basePath: API.INVOICES,
  deletePath: API.INVOICES_DELETE,
})

export const invoiceService = {
  ...baseInvoiceService,

  async convertToEstimate(id: number): Promise<ApiResponse<Record<string, unknown>>> {
    const { data } = await client.post(`${API.INVOICES}/${id}/convert-to-estimate`)
    return data
  },

  async getNextNumber(params?: { key?: string; template_name?: string }): Promise<NextNumberResponse> {
    // When template_name is provided, use the invoice-specific next-number
    // endpoint which calls setTemplateName() on the backend — this ensures
    // the correct per-template format (e.g. lr_receipt_number_format) is used.
    if (params?.template_name) {
      const { data } = await client.get(`${API.INVOICES}/next-number`, { params })
      // InvoicesController returns { success, data: { next_number } }
      // Normalize to the NextNumberResponse format { nextNumber }
      return { nextNumber: data.data.next_number }
    }
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'invoice', ...params } })
    return data
  },

  /**
   * Find an invoice by its invoice_number and template_name.
   * Used by the Office Invoice form to auto-fill Consignment Details
   * from a matching LR Receipt (Docket No = Consignment No).
   */
  async findByInvoiceNumber(
    invoiceNumber: string,
    templateName: string = 'lr_receipt',
  ): Promise<ApiResponse<Invoice> | null> {
    try {
      const { data } = await client.get(`${API.INVOICES}/find-by-number`, {
        params: { invoice_number: invoiceNumber, template_name: templateName },
      })
      return data
    } catch {
      return null
    }
  },

  async getTemplates(documentType?: string): Promise<InvoiceTemplatesResponse> {
    const params: Record<string, string> = {}
    if (documentType) {
      params.document_type = documentType
    }
    const { data } = await client.get(API.INVOICE_TEMPLATES, { params })
    return data
  },
}
