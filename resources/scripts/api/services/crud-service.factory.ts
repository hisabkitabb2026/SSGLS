import { client } from '../client'
import type {
  ApiResponse,
  PaginatedResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'

/**
 * Configuration for the basic CRUD service factory.
 */
export interface BasicCrudConfig {
  /** Base API path, e.g. '/customers' or '/notes' */
  basePath: string
  /** Optional separate endpoint for bulk delete (defaults to basePath) */
  deletePath?: string
  /** If true, delete uses POST to deletePath with DeletePayload body.
   *  If false, delete uses DELETE on `${basePath}/${id}` with id as parameter.
   *  Default: true (matches the majority of existing services). */
  usePostDelete?: boolean
}


/**
 * Generic payload for the `send` action on any document.
 */
export interface SendPayload {
  id: number
  subject?: string | null
  body?: string | null
  from?: string | null
  to?: string | null
  cc?: string | null
  bcc?: string | null
}

/**
 * Payload for the `sendPreview` action.
 */
export interface SendPreviewPayload {
  id: number
  from?: string | null
  to?: string | null
  cc?: string | null
  bcc?: string | null
  subject?: string | null
  body?: string | null
}

/**
 * Payload for changing document status.
 */
export interface StatusPayload {
  id: number
  status: string
}

/**
 * Configuration for the CRUD service factory.
 */
export interface CrudServiceConfig {
  /** Base API path, e.g. '/invoices' or '/estimates' */
  basePath: string
  /** Separate endpoint for bulk delete via POST (defaults to basePath with DELETE) */
  deletePath?: string
  /** Path for the send action (defaults to `${basePath}/:id/send`) */
  sendPath?: (id: number) => string
  /** Path for the clone action (defaults to `${basePath}/:id/clone`) */
  clonePath?: (id: number) => string
  /** Path for the status change action (defaults to `${basePath}/:id/status`) */
  statusPath?: (id: number) => string
}


/**
 * Standard CRUD service interface returned by the factory.
 *
 * Future document services (credit notes, lorry receipts, etc.) can be created
 * by calling `createCrudService<T>()` with the appropriate base path, then
 * extending the returned object with document-specific methods.
 */
export interface CrudService<T, ListParamsT = ListParams, CreatePayloadT = Partial<T>> {
  list(params?: ListParamsT): Promise<PaginatedResponse<T>>
  get(id: number): Promise<ApiResponse<T>>
  create(payload: CreatePayloadT): Promise<ApiResponse<T>>
  update(id: number, payload: Partial<CreatePayloadT>): Promise<ApiResponse<T>>
  delete(payload: DeletePayload): Promise<{ success: boolean }>
  send(payload: SendPayload): Promise<ApiResponse<T>>
  sendPreview(payload: SendPreviewPayload): Promise<ApiResponse<string>>
  clone(id: number): Promise<ApiResponse<T>>
  changeStatus(payload: StatusPayload): Promise<ApiResponse<T>>
}

/**
 * Factory that creates a standard CRUD service object for a given resource.
 *
 * Eliminates the boilerplate of repeating list/get/create/update/delete/send/clone
 * methods in every service file.  Each service can then extend the returned
 * object with document-specific methods (e.g. convertToEstimate, findByInvoiceNumber).
 *
 * @example
 * const baseInvoiceService = createCrudService<Invoice>({ basePath: API.INVOICES })
 * export const invoiceService = {
 *   ...baseInvoiceService,
 *   convertToEstimate(id: number) { ... },
 *   findByInvoiceNumber(num: string) { ... },
 * }
 */
export function createCrudService<
  T,
  ListParamsT extends ListParams = ListParams,
  CreatePayloadT = Partial<T>,
>(config: CrudServiceConfig): CrudService<T, ListParamsT, CreatePayloadT> {
  const { basePath } = config

  const sendPath = config.sendPath ?? ((id: number) => `${basePath}/${id}/send`)
  const clonePath = config.clonePath ?? ((id: number) => `${basePath}/${id}/clone`)
  const statusPath = config.statusPath ?? ((id: number) => `${basePath}/${id}/status`)

  return {
    async list(params?: ListParamsT): Promise<PaginatedResponse<T>> {
      const { data } = await client.get(basePath, { params })
      return data
    },

    async get(id: number): Promise<ApiResponse<T>> {
      const { data } = await client.get(`${basePath}/${id}`)
      return data
    },

    async create(payload: CreatePayloadT): Promise<ApiResponse<T>> {
      const { data } = await client.post(basePath, payload)
      return data
    },

    async update(id: number, payload: Partial<CreatePayloadT>): Promise<ApiResponse<T>> {
      const { data } = await client.put(`${basePath}/${id}`, payload)
      return data
    },

    async delete(payload: DeletePayload): Promise<{ success: boolean }> {
      if (config.deletePath) {
        const { data } = await client.post(config.deletePath, payload)
        return data
      }
      const { data } = await client.delete(basePath, { data: payload })
      return data
    },

    async send(payload: SendPayload): Promise<ApiResponse<T>> {
      const { data } = await client.post(sendPath(payload.id), payload)
      return data
    },

    async sendPreview(payload: SendPreviewPayload): Promise<ApiResponse<string>> {
      const { data } = await client.post(`${sendPath(payload.id)}/preview`, payload)
      return data
    },

    async clone(id: number): Promise<ApiResponse<T>> {
      const { data } = await client.post(clonePath(id))
      return data
    },

    async changeStatus(payload: StatusPayload): Promise<ApiResponse<T>> {
      const { data } = await client.post(statusPath(payload.id), payload)
      return data
    },
  }
}

/**
 * Basic CRUD service interface (list/get/create/update/delete only).
 *
 * Used by non-document resources like customers, notes, items, expenses, etc.
 * that don't need send/clone/changeStatus methods.
 */
export interface BasicCrudService<T, ListResponseT = ApiResponse<T[]>, CreatePayloadT = Partial<T>> {
  list(params?: ListParams): Promise<ListResponseT>
  get(id: number): Promise<ApiResponse<T>>
  create(payload: CreatePayloadT): Promise<ApiResponse<T>>
  update(id: number, payload: Partial<CreatePayloadT>): Promise<ApiResponse<T>>
  delete(payload: DeletePayload | number): Promise<{ success: boolean }>
}

/**
 * Factory that creates a basic CRUD service (list/get/create/update/delete)
 * for non-document resources.
 *
 * Supports two delete patterns:
 * - POST to a separate delete endpoint with DeletePayload (default, used by
 *   customers, expenses, items, etc.)
 * - DELETE on `${basePath}/${id}` (used by notes, tax-types, etc.)
 *
 * @example
 * const baseNoteService = createBasicCrudService<Note>({
 *   basePath: API.NOTES,
 *   usePostDelete: false,
 * })
 * export const noteService = {
 *   ...baseNoteService,
 *   // custom methods...
 * }
 */
export function createBasicCrudService<
  T,
  ListResponseT = ApiResponse<T[]>,
  CreatePayloadT = Partial<T>,
>(config: BasicCrudConfig): BasicCrudService<T, ListResponseT, CreatePayloadT> {
  const { basePath, deletePath, usePostDelete = true } = config
  const delPath = deletePath ?? basePath

  return {
    async list(params?: ListParams): Promise<ListResponseT> {
      const { data } = await client.get(basePath, { params })
      return data
    },

    async get(id: number): Promise<ApiResponse<T>> {
      const { data } = await client.get(`${basePath}/${id}`)
      return data
    },

    async create(payload: CreatePayloadT): Promise<ApiResponse<T>> {
      const { data } = await client.post(basePath, payload)
      return data
    },

    async update(id: number, payload: Partial<CreatePayloadT>): Promise<ApiResponse<T>> {
      const { data } = await client.put(`${basePath}/${id}`, payload)
      return data
    },

    async delete(payload: DeletePayload | number): Promise<{ success: boolean }> {
      if (usePostDelete && typeof payload === 'object') {
        const { data } = await client.post(delPath, payload)
        return data
      }
      // DELETE with id
      const id = typeof payload === 'number' ? payload : payload.id
      const { data } = await client.delete(`${basePath}/${id}`)
      return data
    },
  }
}
