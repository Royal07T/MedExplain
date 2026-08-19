import type { HealthRecord } from '@/types'

import { apiClient } from './client'

export interface ClinicianPatient {
  id: number
  name: string
  email: string
  last_lab_date: string | null
}

export interface GrantAccessResponse {
  data: ClinicianPatient
  created: boolean
}

export async function listPatients(): Promise<ClinicianPatient[]> {
  const { data } = await apiClient.get<{ data: ClinicianPatient[] }>('/clinician/patients')
  return data.data
}

export async function grantPatientAccess(email: string): Promise<GrantAccessResponse> {
  const { data } = await apiClient.post<GrantAccessResponse>('/clinician/patients', { email })
  return data
}

export async function getPatientRecord(patientId: number): Promise<HealthRecord> {
  const { data } = await apiClient.get<{ data: HealthRecord }>(
    `/clinician/patients/${patientId}/record`,
  )
  return data.data
}