import { defineStore } from 'pinia'

import * as medicationsApi from '@/api/medications'
import type { Medication } from '@/types'

interface MedicationsState {
  medications: Medication[]
  loading: boolean
  error: string | null
}

export const useMedicationsStore = defineStore('medications', {
  state: (): MedicationsState => ({
    medications: [],
    loading: false,
    error: null,
  }),

  actions: {
    async fetch() {
      this.loading = true
      this.error = null
      try {
        this.medications = await medicationsApi.listMedications()
      } catch {
        this.error = 'Unable to load medications.'
      } finally {
        this.loading = false
      }
    },
  },
})