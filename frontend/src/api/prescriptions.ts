import { apiClient } from './client'

export interface Prescription {
  id: number
  medication_name: string
  status: string
  ordered_at: string
  expires_at: string | null
  dispensed_at: string | null
  notes: string | null
  clinician_name: string | null
  user_name: string | null
}

export interface CreatePrescriptionRequest {
  patient_id: number
  medication_id: number
  status?: string
  notes?: string
  expires_at?: string
}

export async function getPatientPrescriptions(patientId: number): Promise<Prescription[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Prescription[] }>(`/clinician/patients/${patientId}/prescriptions`)
  return data.data
}

export async function createPrescription(request: CreatePrescriptionRequest): Promise<Prescription> {
  const { data } = await apiClient.post<{ success: boolean; data: Prescription }>('/clinician/prescriptions', request)
  return data.data
}

export async function updatePrescriptionStatus(id: number, status: string): Promise<Prescription> {
  const { data } = await apiClient.put<{ success: boolean; data: Prescription }>(`/clinician/prescriptions/${id}/status`, { status })
  return data.data
}

export async function getPrescription(id: number): Promise<Prescription> {
  const { data } = await apiClient.get<{ success: boolean; data: Prescription }>(`/clinician/prescriptions/${id}`)
  return data.data
}
