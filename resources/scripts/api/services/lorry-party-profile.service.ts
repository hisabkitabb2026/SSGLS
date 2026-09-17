import { client } from '../client'
import { API } from '../endpoints'
import type { LorryPartyProfile, LorryPartyProfilePayload } from '@/scripts/types/domain/lorry-party-profile'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface LorryPartyProfileListParams extends ListParams {
  type?: string
}

export interface LorryPartyProfileListResponse {
  data: LorryPartyProfile[]
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export const lorryPartyProfileService = {
  async list(params?: LorryPartyProfileListParams): Promise<LorryPartyProfileListResponse> {
    const { data } = await client.get(API.LORRY_PARTY_PROFILES, { params })
    return data
  },

  async get(id: number): Promise<ApiResponse<LorryPartyProfile>> {
    const { data } = await client.get(`${API.LORRY_PARTY_PROFILES}/${id}`)
    return data
  },

  async create(payload: LorryPartyProfilePayload): Promise<ApiResponse<LorryPartyProfile>> {
    const { data } = await client.post(API.LORRY_PARTY_PROFILES, payload)
    return data
  },

  async update(id: number, payload: Partial<LorryPartyProfilePayload>): Promise<ApiResponse<LorryPartyProfile>> {
    const { data } = await client.put(`${API.LORRY_PARTY_PROFILES}/${id}`, payload)
    return data
  },

  async delete(id: number): Promise<{ success: boolean }> {
    const { data } = await client.delete(`${API.LORRY_PARTY_PROFILES}/${id}`)
    return data
  },
}
