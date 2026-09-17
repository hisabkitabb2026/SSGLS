import { client } from '../client'
import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { Item, Unit } from '@/scripts/types/domain/item'
import type {
  ApiResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'

export interface ItemListParams extends ListParams {
  filter?: Record<string, unknown>
}

export interface ItemListMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  item_total_count: number
}

export interface ItemListResponse {
  data: Item[]
  meta: ItemListMeta
}

export interface CreateItemPayload {
  name: string
  description?: string | null
  price: number
  unit_id?: number | null
  taxes?: Array<{ tax_type_id: number }>
}

export interface CreateUnitPayload {
  name: string
}

const baseItemService = createBasicCrudService<
  Item,
  ItemListResponse,
  CreateItemPayload
>({
  basePath: API.ITEMS,
  deletePath: API.ITEMS_DELETE,
  usePostDelete: true,
})

export const itemService = {
  ...baseItemService,

  async list(params?: ItemListParams): Promise<ItemListResponse> {
    return baseItemService.list(params) as Promise<ItemListResponse>
  },

  async delete(payload: DeletePayload): Promise<{ success: boolean }> {
    return baseItemService.delete(payload) as Promise<{ success: boolean }>
  },

  // Units
  async listUnits(params?: ListParams): Promise<ApiResponse<Unit[]>> {
    const { data } = await client.get(API.UNITS, { params })
    return data
  },

  async getUnit(id: number): Promise<ApiResponse<Unit>> {
    const { data } = await client.get(`${API.UNITS}/${id}`)
    return data
  },

  async createUnit(payload: CreateUnitPayload): Promise<ApiResponse<Unit>> {
    const { data } = await client.post(API.UNITS, payload)
    return data
  },

  async updateUnit(id: number, payload: CreateUnitPayload): Promise<ApiResponse<Unit>> {
    const { data } = await client.put(`${API.UNITS}/${id}`, payload)
    return data
  },

  async deleteUnit(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.UNITS}/${id}`)
    return data
  },
}
