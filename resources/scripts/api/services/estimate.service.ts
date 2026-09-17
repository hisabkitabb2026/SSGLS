import { client } from '../client'
import { API } from '../endpoints'
import { createCrudService, type SendPayload, type StatusPayload } from './crud-service.factory'
import type { Estimate, CreateEstimatePayload } from '@/scripts/types/domain/estimate'
import type { Invoice } from '@/scripts/types/domain/invoice'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  NextNumberResponse,
} from '@/scripts/types/api'

export interface EstimateListParams extends ListParams, DateRangeParams {
  status?: string
  customer_id?: number
}

export interface EstimateListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  estimate_total_count: number
}

export interface EstimateListResponse {
  data: Estimate[]
  meta: EstimateListMeta
}

/** @deprecated Use SendPayload from crud-service.factory.ts */
export type SendEstimatePayload = SendPayload

/** @deprecated Use StatusPayload from crud-service.factory.ts */
export type EstimateStatusPayload = StatusPayload

export interface EstimateTemplate {
  name: string
  path: string
}

export interface EstimateTemplatesResponse {
  estimateTemplates: EstimateTemplate[]
}

// Use the factory for standard CRUD + send/clone/changeStatus methods
const baseEstimateService = createCrudService<Estimate, EstimateListParams, CreateEstimatePayload>({
  basePath: API.ESTIMATES,
  deletePath: API.ESTIMATES_DELETE,
})

export const estimateService = {
  ...baseEstimateService,

  async convertToInvoice(id: number): Promise<ApiResponse<Invoice>> {
    const { data } = await client.post(`${API.ESTIMATES}/${id}/convert-to-invoice`)
    return data
  },

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'estimate', ...params } })
    return data
  },

  async getTemplates(): Promise<EstimateTemplatesResponse> {
    const { data } = await client.get(API.ESTIMATE_TEMPLATES)
    return data
  },
}
