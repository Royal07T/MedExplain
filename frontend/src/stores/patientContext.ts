import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiClient } from '@/api/client'

export interface PatientContext {
  patient_id: number
  patient_user_id: number
  mrn: string
  full_name: string
  date_of_birth: string | null
  gender: string | null
  phone: string | null
  email: string | null
}

export interface PatientSearchResult {
  id: number
  user_id: number
  mrn: string
  full_name: string
  date_of_birth: string | null
}

export const usePatientContextStore = defineStore('patientContext', () => {
  const currentContext = ref<PatientContext | null>(null)
  const searchResults = ref<PatientSearchResult[]>([])
  const loading = ref(false)
  const searching = ref(false)

  async function selectContext(patientId: number) {
    loading.value = true
    try {
      const { data } = await apiClient.post<{ data: PatientContext }>('/patient-context/select', {
        patient_id: patientId,
      })
      currentContext.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function clearContext() {
    loading.value = true
    try {
      await apiClient.delete('/patient-context')
      currentContext.value = null
    } finally {
      loading.value = false
    }
  }

  async function fetchCurrentContext() {
    loading.value = true
    try {
      const { data } = await apiClient.get<{ data: PatientContext | null }>('/patient-context')
      currentContext.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function searchPatients(query: string) {
    if (query.length < 2) {
      searchResults.value = []
      return
    }
    searching.value = true
    try {
      const { data } = await apiClient.get<{ data: PatientSearchResult[] }>('/patient-context/search', {
        params: { query },
      })
      searchResults.value = data.data
    } finally {
      searching.value = false
    }
  }

  return {
    currentContext,
    searchResults,
    loading,
    searching,
    selectContext,
    clearContext,
    fetchCurrentContext,
    searchPatients,
  }
})
