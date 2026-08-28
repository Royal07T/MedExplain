import { apiClient } from './client'

export interface Problem {
  id: number
  patient_id: number
  icd10_code: string
  icd10_description: string
  clinical_notes: string | null
  status: 'active' | 'chronic' | 'resolved'
  onset_date: string
  resolved_date: string | null
  created_at: string
}

export interface CreateProblemRequest {
  patient_id: number
  icd10_code: string
  icd10_description: string
  clinical_notes?: string
  status: Problem['status']
  onset_date: string
}

export async function getPatientProblems(patientId: number): Promise<Problem[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Problem[] }>(`/clinician/patients/${patientId}/problems`)
  return data.data
}

export async function createProblem(request: CreateProblemRequest): Promise<Problem> {
  const { data } = await apiClient.post<{ success: boolean; data: Problem }>('/clinician/problems', request)
  return data.data
}

export async function updateProblem(id: number, request: Partial<CreateProblemRequest>): Promise<Problem> {
  const { data } = await apiClient.put<{ success: boolean; data: Problem }>(`/clinician/problems/${id}`, request)
  return data.data
}

export async function deleteProblem(id: number): Promise<void> {
  await apiClient.delete(`/clinician/problems/${id}`)
}
