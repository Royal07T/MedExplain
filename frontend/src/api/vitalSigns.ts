import { apiClient } from './client'

export interface VitalSign {
  id: number
  patient_id: number
  encounter_id: number | null
  temperature: number | null
  temperature_unit: 'C' | 'F'
  heart_rate: number | null
  blood_pressure_systolic: number | null
  blood_pressure_diastolic: number | null
  respiratory_rate: number | null
  oxygen_saturation: number | null
  weight: number | null
  weight_unit: 'kg' | 'lb'
  height: number | null
  height_unit: 'cm' | 'in'
  bmi: number | null
  pain_score: number | null
  notes: string | null
  recorded_by: number
  recorded_at: string
}

export interface CreateVitalSignRequest {
  patient_id: number
  encounter_id?: number
  temperature?: number
  temperature_unit?: 'C' | 'F'
  heart_rate?: number
  blood_pressure_systolic?: number
  blood_pressure_diastolic?: number
  respiratory_rate?: number
  oxygen_saturation?: number
  weight?: number
  weight_unit?: 'kg' | 'lb'
  height?: number
  height_unit?: 'cm' | 'in'
  pain_score?: number
  notes?: string
  recorded_at?: string
}

export async function getPatientVitalSigns(patientId: number): Promise<VitalSign[]> {
  const { data } = await apiClient.get<{ success: boolean; data: VitalSign[] }>(`/clinician/patients/${patientId}/vital-signs`)
  return data.data
}

export async function createVitalSign(request: CreateVitalSignRequest): Promise<VitalSign> {
  const { data } = await apiClient.post<{ success: boolean; data: VitalSign }>('/clinician/vital-signs', request)
  return data.data
}

export async function updateVitalSign(id: number, request: Partial<CreateVitalSignRequest>): Promise<VitalSign> {
  const { data } = await apiClient.put<{ success: boolean; data: VitalSign }>(`/clinician/vital-signs/${id}`, request)
  return data.data
}

export async function deleteVitalSign(id: number): Promise<void> {
  await apiClient.delete(`/clinician/vital-signs/${id}`)
}
