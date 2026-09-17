import { client } from '../client'
import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { Role, Ability } from '@/scripts/types/domain/role'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface CreateRolePayload {
  name: string
  abilities: Array<{
    ability: string
    model?: string | null
  }>
}

export interface AbilitiesResponse {
  abilities: Ability[]
}

const baseRoleService = createBasicCrudService<Role, ApiResponse<Role[]>, CreateRolePayload>({
  basePath: API.ROLES,
  usePostDelete: false,
})

export const roleService = {
  ...baseRoleService,

  async list(params?: ListParams): Promise<ApiResponse<Role[]>> {
    return baseRoleService.list(params) as Promise<ApiResponse<Role[]>>
  },

  // Override create because the API returns { role: Role }, not ApiResponse<Role>
  async create(payload: CreateRolePayload): Promise<{ role: Role }> {
    const { data } = await client.post(API.ROLES, payload)
    return data
  },

  async getAbilities(params?: ListParams): Promise<AbilitiesResponse> {
    const { data } = await client.get(API.ABILITIES, { params })
    return data
  },
}
