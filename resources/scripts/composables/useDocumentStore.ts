import { ref, computed, type Ref, type ComputedRef } from 'vue'
import type {
  ApiResponse,
  PaginatedResponse,
  ListParams,
  DeletePayload,
} from '@/scripts/types/api'
import type { CrudService } from '@/scripts/api/services/crud-service.factory'

/**
 * Options for the useDocumentStore composable.
 */
export interface UseDocumentStoreOptions<T, ListParamsT = ListParams, CreatePayloadT = Partial<T>> {
  /** The CRUD service instance for this document type */
  service: CrudService<T, ListParamsT, CreatePayloadT>
  /** Default list params when fetching documents */
  defaultParams?: Partial<ListParamsT>
}

/**
 * Return type of the useDocumentStore composable.
 */
export interface UseDocumentStoreReturn<T, ListParamsT = ListParams, CreatePayloadT = Partial<T>> {
  // State
  documents: Ref<T[]>
  currentDocument: Ref<T | null>
  loading: Ref<boolean>
  error: Ref<string | null>
  totalCount: Ref<number>
  currentPage: Ref<number>
  params: Ref<Partial<ListParamsT>>

  // Computed
  hasDocuments: ComputedRef<boolean>

  // Actions
  fetchDocuments(params?: Partial<ListParamsT>): Promise<void>
  fetchDocument(id: number): Promise<T | null>
  createDocument(payload: CreatePayloadT): Promise<T | null>
  updateDocument(id: number, payload: Partial<CreatePayloadT>): Promise<T | null>
  deleteDocuments(ids: number[]): Promise<boolean>
  sendDocument(id: number, emailData: Record<string, unknown>): Promise<boolean>
  cloneDocument(id: number): Promise<T | null>
  changeDocumentStatus(id: number, status: string): Promise<boolean>
  resetState(): void
}

/**
 * Generic composable for document-type Pinia stores.
 *
 * Eliminates the ~70% duplicated state/actions boilerplate between
 * invoice store, estimate store, and payment store.  Each store provides
 * its CrudService instance and gets back reactive state + actions.
 *
 * @example
 * const invoiceStore = useDocumentStore<Invoice>({
 *   service: invoiceService,
 *   defaultParams: { limit: 10 },
 * })
 *
 * // In a component:
 * const { documents, loading, fetchDocuments, createDocument } = invoiceStore
 */
export function useDocumentStore<T, ListParamsT extends ListParams = ListParams, CreatePayloadT = Partial<T>>(
  options: UseDocumentStoreOptions<T, ListParamsT, CreatePayloadT>
): UseDocumentStoreReturn<T, ListParamsT, CreatePayloadT> {
  const { service, defaultParams = {} } = options

  // --- Reactive State ---
  const documents = ref<T[]>([]) as Ref<T[]>
  const currentDocument = ref<T | null>(null) as Ref<T | null>
  const loading = ref(false)
  const error = ref<string | null>(null)
  const totalCount = ref(0)
  const currentPage = ref(1)
  const params = ref<Partial<ListParamsT>>({ ...defaultParams }) as Ref<Partial<ListParamsT>>

  // --- Computed ---
  const hasDocuments = computed(() => documents.value.length > 0)

  // --- Actions ---
  async function fetchDocuments(fetchParams?: Partial<ListParamsT>): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const mergedParams = { ...params.value, ...fetchParams } as ListParamsT
      const response: PaginatedResponse<T> = await service.list(mergedParams)
      documents.value = response.data
      if (response.meta) {
        totalCount.value = response.meta.total ?? response.data.length
        currentPage.value = response.meta.current_page ?? 1
      }
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch documents'
    } finally {
      loading.value = false
    }
  }

  async function fetchDocument(id: number): Promise<T | null> {
    loading.value = true
    error.value = null
    try {
      const response: ApiResponse<T> = await service.get(id)
      currentDocument.value = response.data
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to fetch document'
      return null
    } finally {
      loading.value = false
    }
  }

  async function createDocument(payload: CreatePayloadT): Promise<T | null> {
    loading.value = true
    error.value = null
    try {
      const response: ApiResponse<T> = await service.create(payload)
      documents.value.unshift(response.data)
      totalCount.value += 1
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to create document'
      return null
    } finally {
      loading.value = false
    }
  }

  async function updateDocument(id: number, payload: Partial<CreatePayloadT>): Promise<T | null> {
    loading.value = true
    error.value = null
    try {
      const response: ApiResponse<T> = await service.update(id, payload)
      const index = documents.value.findIndex(
        (doc) => (doc as Record<string, unknown>).id === id
      )
      if (index !== -1) {
        documents.value[index] = response.data
      }
      if (currentDocument.value && (currentDocument.value as Record<string, unknown>).id === id) {
        currentDocument.value = response.data
      }
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to update document'
      return null
    } finally {
      loading.value = false
    }
  }

  async function deleteDocuments(ids: number[]): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      const payload: DeletePayload = { ids }
      await service.delete(payload)
      documents.value = documents.value.filter(
        (doc) => !ids.includes((doc as Record<string, unknown>).id as number)
      )
      totalCount.value -= ids.length
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to delete documents'
      return false
    } finally {
      loading.value = false
    }
  }

  async function sendDocument(id: number, emailData: Record<string, unknown>): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      await service.send({ id, ...emailData } as never)
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to send document'
      return false
    } finally {
      loading.value = false
    }
  }

  async function cloneDocument(id: number): Promise<T | null> {
    loading.value = true
    error.value = null
    try {
      const response: ApiResponse<T> = await service.clone(id)
      documents.value.unshift(response.data)
      totalCount.value += 1
      return response.data
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to clone document'
      return null
    } finally {
      loading.value = false
    }
  }

  async function changeDocumentStatus(id: number, status: string): Promise<boolean> {
    loading.value = true
    error.value = null
    try {
      await service.changeStatus({ id, status })
      return true
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'Failed to change status'
      return false
    } finally {
      loading.value = false
    }
  }

  function resetState(): void {
    documents.value = []
    currentDocument.value = null
    loading.value = false
    error.value = null
    totalCount.value = 0
    currentPage.value = 1
    params.value = { ...defaultParams } as Partial<ListParamsT>
  }

  return {
    documents,
    currentDocument,
    loading,
    error,
    totalCount,
    currentPage,
    params,
    hasDocuments,
    fetchDocuments,
    fetchDocument,
    createDocument,
    updateDocument,
    deleteDocuments,
    sendDocument,
    cloneDocument,
    changeDocumentStatus,
    resetState,
  }
}
