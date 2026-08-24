import type { Analysis, Document, Paginated } from '@/types'

import { apiClient } from './client'

export async function listDocuments(page = 1): Promise<Paginated<Document>> {
  const { data } = await apiClient.get<Paginated<Document>>('/documents', { params: { page } })
  return data
}

export async function getDocument(id: number): Promise<Document> {
  const { data } = await apiClient.get<Document>(`/documents/${id}`)
  return data
}

export async function uploadDocument(
  file: File,
  onProgress?: (percent: number) => void,
): Promise<Document> {
  const form = new FormData()
  form.append('file', file)

  try {
    const { data } = await apiClient.post<Document>('/documents', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (event) => {
        if (onProgress && event.total) {
          onProgress(Math.round((event.loaded / event.total) * 100))
        }
      },
    })

    return data
  } catch (error: any) {
    if (error.response?.status === 422 && error.response?.data?.errors) {
      const validationErrors = error.response.data.errors
      const errorMessage = Object.values(validationErrors).flat().join('. ')
      throw new Error(errorMessage || 'Upload failed due to validation errors')
    }
    throw error
  }
}

export async function deleteDocument(id: number): Promise<void> {
  await apiClient.delete(`/documents/${id}`)
}

export async function getAnalysis(id: number): Promise<Analysis> {
  const { data } = await apiClient.get<Analysis>(`/documents/${id}/analysis`)
  return data
}