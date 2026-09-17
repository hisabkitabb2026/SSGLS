import { client } from '../client'
import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { Customer, CreateCustomerPayload } from '@/scripts/types/domain/customer'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'

export interface CustomerListParams extends ListParams {
  display_name?: string
}

export interface CustomerListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  customer_total_count: number
}

export interface CustomerListResponse {
  data: Customer[]
  meta: CustomerListMeta
}

export interface CustomerStatsChartData {
  salesTotal: number
  totalReceipts: number
  totalExpenses: number
  netProfit: number
  expenseTotals: number[]
  netProfits: number[]
  months: string[]
  receiptTotals: number[]
  invoiceTotals: number[]
}

export interface CustomerStatsParams {
  previous_year?: boolean
  this_year?: boolean
}

export interface CustomerStatsResponse {
  data: Customer
  meta: {
    chartData: CustomerStatsChartData
  }
}

const baseCustomerService = createBasicCrudService<
  Customer,
  CustomerListResponse,
  CreateCustomerPayload
>({
  basePath: API.CUSTOMERS,
  deletePath: API.CUSTOMERS_DELETE,
  usePostDelete: true,
})

export const customerService = {
  ...baseCustomerService,

  async list(params?: CustomerListParams): Promise<CustomerListResponse> {
    return baseCustomerService.list(params) as Promise<CustomerListResponse>
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    return baseCustomerService.delete(payload) as Promise<{ success: boolean }>
  },

  async getStats(
    id: number,
    params?: CustomerStatsParams
  ): Promise<CustomerStatsResponse> {
    const { data } = await client.get(`${API.CUSTOMER_STATS}/${id}/stats`, { params })
    return data
  },
}
