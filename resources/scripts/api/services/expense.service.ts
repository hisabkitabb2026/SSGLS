import { client } from '../client'
import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { Expense, ExpenseCategory, CreateExpensePayload } from '@/scripts/types/domain/expense'
import type {
  ApiResponse,
  ListParams,
  DateRangeParams,
  DeletePayload,
} from '@/scripts/types/api'

export interface ExpenseListParams extends ListParams, DateRangeParams {
  expense_category_id?: number
  customer_id?: number
}

export interface ExpenseListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  expense_total_count: number
}

export interface ExpenseListResponse {
  data: Expense[]
  meta: ExpenseListMeta
}

export interface CreateExpenseCategoryPayload {
  name: string
  description?: string | null
}

const baseExpenseService = createBasicCrudService<
  Expense,
  ExpenseListResponse,
  CreateExpensePayload
>({
  basePath: API.EXPENSES,
  deletePath: API.EXPENSES_DELETE,
  usePostDelete: true,
})

export const expenseService = {
  ...baseExpenseService,

  async list(params?: ExpenseListParams): Promise<ExpenseListResponse> {
    return baseExpenseService.list(params) as Promise<ExpenseListResponse>
  },

  async create(payload: FormData): Promise<ApiResponse<Expense>> {
    const { data } = await client.post(API.EXPENSES, payload)
    return data
  },

  async update(id: number, payload: FormData): Promise<ApiResponse<Expense>> {
    const { data } = await client.post(`${API.EXPENSES}/${id}`, payload)
    return data
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    return baseExpenseService.delete(payload) as Promise<{ success: boolean }>
  },

  async showReceipt(id: number): Promise<Blob> {
    const { data } = await client.get(`${API.EXPENSES}/${id}/show/receipt`, {
      responseType: 'blob',
    })
    return data
  },

  async uploadReceipt(id: number, payload: FormData): Promise<ApiResponse<Expense>> {
    const { data } = await client.post(`${API.EXPENSES}/${id}/upload/receipts`, payload)
    return data
  },

  // Expense Categories
  async listCategories(params?: ListParams): Promise<ApiResponse<ExpenseCategory[]>> {
    const { data } = await client.get(API.CATEGORIES, { params })
    return data
  },

  async getCategory(id: number): Promise<ApiResponse<ExpenseCategory>> {
    const { data } = await client.get(`${API.CATEGORIES}/${id}`)
    return data
  },

  async createCategory(payload: CreateExpenseCategoryPayload): Promise<ApiResponse<ExpenseCategory>> {
    const { data } = await client.post(API.CATEGORIES, payload)
    return data
  },

  async updateCategory(
    id: number,
    payload: CreateExpenseCategoryPayload,
  ): Promise<ApiResponse<ExpenseCategory>> {
    const { data } = await client.put(`${API.CATEGORIES}/${id}`, payload)
    return data
  },

  async deleteCategory(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.CATEGORIES}/${id}`)
    return data
  },
}
