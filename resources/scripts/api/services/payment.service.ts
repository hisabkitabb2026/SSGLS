import { client } from '../client'
import { API } from '../endpoints'
import { createCrudService, type SendPayload } from './crud-service.factory'
import type { Payment, PaymentMethod, CreatePaymentPayload } from '@/scripts/types/domain/payment'
import type {
  ApiResponse,
  ListParams,
  NextNumberResponse,
} from '@/scripts/types/api'

export interface PaymentListParams extends ListParams {
  customer_id?: number
  from_date?: string
  to_date?: string
}

export interface PaymentListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  payment_total_count: number
}

export interface PaymentListResponse {
  data: Payment[]
  meta: PaymentListMeta
}

/** @deprecated Use SendPayload from crud-service.factory.ts */
export type SendPaymentPayload = SendPayload

export interface CreatePaymentMethodPayload {
  name: string
}

// Use the factory for standard CRUD + send/clone/changeStatus methods
const basePaymentService = createCrudService<Payment, PaymentListParams, CreatePaymentPayload>({
  basePath: API.PAYMENTS,
  deletePath: API.PAYMENTS_DELETE,
})

export const paymentService = {
  ...basePaymentService,

  async getNextNumber(params?: { key?: string }): Promise<NextNumberResponse> {
    const { data } = await client.get(API.NEXT_NUMBER, { params: { key: 'payment', ...params } })
    return data
  },

  // Payment Methods
  async listMethods(params?: ListParams): Promise<ApiResponse<PaymentMethod[]>> {
    const { data } = await client.get(API.PAYMENT_METHODS, { params })
    return data
  },

  async getMethod(id: number): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.get(`${API.PAYMENT_METHODS}/${id}`)
    return data
  },

  async createMethod(payload: CreatePaymentMethodPayload): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.post(API.PAYMENT_METHODS, payload)
    return data
  },

  async updateMethod(id: number, payload: CreatePaymentMethodPayload): Promise<ApiResponse<PaymentMethod>> {
    const { data } = await client.put(`${API.PAYMENT_METHODS}/${id}`, payload)
    return data
  },

  async deleteMethod(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.PAYMENT_METHODS}/${id}`)
    return data
  },
}
