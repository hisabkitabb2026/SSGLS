import { client } from '../client'
import { API } from '../endpoints'
import { createCrudService, type SendPayload, type StatusPayload } from './crud-service.factory'
import type { Quotation, CreateQuotationPayload } from '@/scripts/types/domain/quotation'
import type { Invoice } from '@/scripts/types/domain/invoice'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
} from '@/scripts/types/api'

export interface QuotationListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface QuotationListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  quotation_total_count: number
}

export interface QuotationListResponse {
  data: Quotation[]
  meta: QuotationListMeta
}

/** @deprecated Use SendPayload from crud-service.factory.ts */
export type SendQuotationPayload = SendPayload

/** @deprecated Use StatusPayload from crud-service.factory.ts */
export type QuotationStatusPayload = StatusPayload

export interface QuotationTemplate {
  name: string
  path: string
}

export interface QuotationTemplatesResponse {
  quotationTemplates: QuotationTemplate[]
}

// Use the factory for standard CRUD + send/clone/changeStatus methods
// NOTE: Quotations reuse the Estimates API on the backend since they share the same controller
const baseQuotationService = createCrudService<Quotation, QuotationListParams, CreateQuotationPayload>({
  basePath: API.ESTIMATES,
  deletePath: API.ESTIMATES_DELETE,
})

export const quotationService = {
  ...baseQuotationService,

  async convertToInvoice(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.ESTIMATES}/${id}/convert-to-invoice`)
    return data
  },

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'quotation', ...params } })
    return data
  },

  async getTemplates(): Promise<QuotationTemplatesResponse> {
    const { data } = await client.get(API.ESTIMATE_TEMPLATES)
    return data
  },
}
