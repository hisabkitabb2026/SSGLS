import { client } from '../client'

export interface CustomTemplate {
  name: string
  path: string
  custom: boolean
}

export interface CustomTemplatesResponse {
  customTemplates: CustomTemplate[]
  builtinTemplates: CustomTemplate[]
}

export const customTemplateService = {
  async list(documentType: string): Promise<CustomTemplatesResponse> {
    const { data } = await client.get('/api/v1/custom-templates', {
      params: { document_type: documentType },
    })
    return data
  },

  async download(templateName: string): Promise<Blob> {
    const { data } = await client.get(
      `/api/v1/custom-templates/${templateName}/download`,
      { responseType: 'blob' },
    )
    return data
  },

  async downloadBuiltin(documentType: string): Promise<Blob> {
    const { data } = await client.get(
      `/api/v1/custom-templates/builtin/${documentType}/download`,
      { responseType: 'blob' },
    )
    return data
  },

  async upload(
    name: string,
    documentType: string,
    file: File,
  ): Promise<{ message: string; template: { name: string; custom: boolean } }> {
    const formData = new FormData()
    formData.append('name', name)
    formData.append('document_type', documentType)
    formData.append('file', file)
    const { data } = await client.post('/api/v1/custom-templates', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data
  },

  async delete(templateName: string): Promise<{ message: string }> {
    const { data } = await client.delete(
      `/api/v1/custom-templates/${templateName}`,
    )
    return data
  },
}
