import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { TaxType } from '@/scripts/types/domain/tax'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface CreateTaxTypePayload {
  name: string
  percent: number
  fixed_amount?: number
  calculation_type?: string | null
  compound_tax?: boolean
  collective_tax?: number | null
  description?: string | null
}

const baseTaxTypeService = createBasicCrudService<TaxType, ApiResponse<TaxType[]>, CreateTaxTypePayload>({
  basePath: API.TAX_TYPES,
  usePostDelete: false,
})

export const taxTypeService = {
  ...baseTaxTypeService,

  async list(params?: ListParams): Promise<ApiResponse<TaxType[]>> {
    return baseTaxTypeService.list(params) as Promise<ApiResponse<TaxType[]>>
  },
}
