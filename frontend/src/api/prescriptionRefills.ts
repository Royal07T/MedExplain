import { apiClient } from './client'

export interface PrescriptionRefill {
  id: number
  patient_id: number
  clinician_id: number
  organization_id: number
  medication_name: string
  dosage: string | null
  frequency: string | null
  reason: string | null
  status: 'pending' | 'approved' | 'denied' | 'filled'
  clinician_notes: string | null
  requested_at: string
  responded_at: string | null
}

export interface CreatePrescriptionRefillRequest {
  clinician_id: number
  medication_name: string
  dosage?: string
  frequency?: string
  reason: string
}

export async function getMyPrescriptionRefills(): Promise<PrescriptionRefill[]> {
  const { data } = await apiClient.get<{ success: boolean; data: PrescriptionRefill[] }>('/patient/prescription-refills')
  return data.data
}

export async function createPrescriptionRefill(request: CreatePrescriptionRefillRequest): Promise<PrescriptionRefill> {
  const { data } = await apiClient.post<{ success: boolean; data: PrescriptionRefill }>('/patient/prescription-refills', request)
  return data.data
}

export async function getPrescriptionRefill(id: number): Promise<PrescriptionRefill> {
  const { data } = await apiClient.get<{ success: boolean; data: PrescriptionRefill }>(`/patient/prescription-refills/${id}`)
  return data.data
}

export async function updatePrescriptionRefill(id: number, status: PrescriptionRefill['status'], clinicianNotes?: string): Promise<PrescriptionRefill> {
  const { data } = await apiClient.put<{ success: boolean; data: PrescriptionRefill }>(`/clinician/prescription-refills/${id}`, {
    status,
    clinician_notes: clinicianNotes,
  })
  return data.data
}

export async function getClinicianPrescriptionRefills(): Promise<PrescriptionRefill[]> {
  const { data } = await apiClient.get<{ success: boolean; data: PrescriptionRefill[] }>('/clinician/prescription-refills')
  return data.data
}
