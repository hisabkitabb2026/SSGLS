import { client } from '../client'
import { API } from '../endpoints'
import { createBasicCrudService } from './crud-service.factory'
import type { Note } from '@/scripts/types/domain/note'
import type { ApiResponse, ListParams } from '@/scripts/types/api'

export interface CreateNotePayload {
  type: string
  name: string
  notes: string
  is_default?: boolean
}

const baseNoteService = createBasicCrudService<Note>({
  basePath: API.NOTES,
  usePostDelete: false,
})

export const noteService = {
  ...baseNoteService,

  async list(params?: ListParams): Promise<ApiResponse<Note[]>> {
    return baseNoteService.list(params) as Promise<ApiResponse<Note[]>>
  },

  // Override create because the API returns the Note directly, not ApiResponse<Note>
  async create(payload: CreateNotePayload): Promise<Note> {
    const { data } = await client.post(API.NOTES, payload)
    return data
  },
}
