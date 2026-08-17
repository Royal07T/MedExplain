import { afterEach, describe, expect, it, vi } from 'vitest'

import * as documentsApi from '@/api/documents'
import { apiClient } from '@/api/client'
import type { Document, Paginated } from '@/types'

const document: Document = {
  id: 1,
  original_filename: 'lab.pdf',
  document_type: 'lab_report',
  file_size: 2048,
  status: 'uploaded',
  extraction_method: null,
  error_message: null,
  created_at: '2026-01-01T00:00:00Z',
}

function axiosLike(data: unknown) {
  return { data, status: 200, statusText: 'OK', headers: {}, config: {} }
}

describe('documents api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('lists documents with pagination params', async () => {
    const payload: Paginated<Document> = {
      data: [document],
      links: {},
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }
    const spy = vi.spyOn(apiClient, 'get').mockResolvedValue(axiosLike(payload) as never)

    const result = await documentsApi.listDocuments(1)

    expect(spy).toHaveBeenCalledWith('/documents', { params: { page: 1 } })
    expect(result.data).toHaveLength(1)
    expect(result.data[0].original_filename).toBe('lab.pdf')
  })

  it('uploads a file as multipart form data', async () => {
    const spy = vi
      .spyOn(apiClient, 'post')
      .mockResolvedValue(axiosLike(document) as never)

    const file = new File(['%PDF-1.4'], 'lab.pdf', { type: 'application/pdf' })
    const result = await documentsApi.uploadDocument(file)

    expect(spy).toHaveBeenCalledTimes(1)
    const [url, form] = spy.mock.calls[0] as unknown as [string, FormData]
    expect(url).toBe('/documents')
    expect(form.get('file')).toBeInstanceOf(File)
    expect(result.id).toBe(1)
  })

  it('deletes a document by id', async () => {
    const spy = vi.spyOn(apiClient, 'delete').mockResolvedValue(axiosLike(null) as never)

    await documentsApi.deleteDocument(7)

    expect(spy).toHaveBeenCalledWith('/documents/7')
  })
})