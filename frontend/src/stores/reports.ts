import { defineStore } from 'pinia'

import * as documentsApi from '@/api/documents'
import type { Document } from '@/types'

interface ReportsState {
  documents: Document[]
  pagination: { currentPage: number; lastPage: number; total: number } | null
  loading: boolean
  error: string | null
}

export const useReportsStore = defineStore('reports', {
  state: (): ReportsState => ({
    documents: [],
    pagination: null,
    loading: false,
    error: null,
  }),

  getters: {
    processedCount: (state) => state.documents.filter((d) => d.status === 'processed').length,
  },

  actions: {
    async fetch(page = 1) {
      this.loading = true
      this.error = null
      try {
        const result = await documentsApi.listDocuments(page)
        this.documents = result.data
        this.pagination = {
          currentPage: result.meta.current_page,
          lastPage: result.meta.last_page,
          total: result.meta.total,
        }
      } catch {
        this.error = 'Unable to load documents.'
      } finally {
        this.loading = false
      }
    },

    async remove(id: number) {
      await documentsApi.deleteDocument(id)
      this.documents = this.documents.filter((d) => d.id !== id)
    },
  },
})