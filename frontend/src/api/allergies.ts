import { apiClient } from './client'

export interface Allergy {
  id: number
  patient_id: number
  allergen_type: 'drug' | 'food' | 'environmental' | 'other'
  allergen_name: string
  reaction_description: string
  severity: 'mild' | 'moderate' | 'severe' | 'life_threatening'
  status: 'active' | 'resolved'
  onset_date: string | null
  notes: string | null
  created_at: string
}

export interface CreateAllergyRequest {
  patient_id: number
  allergen_type: Allergy['allergen_type']
  allergen_name: string
  reaction_description: string
  severity: Allergy['severity']
  status: Allergy['status']
  onset_date?: string
  notes?: string
}

export async function getPatientAllergies(patientId: number): Promise<Allergy[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Allergy[] }>(`/clinician/clinical/patients/${patientId}/allergies`)
  return data.data
}

export async function createAllergy(request: CreateAllergyRequest): Promise<Allergy> {
  const { data } = await apiClient.post<{ success: boolean; data: Allergy }>('/clinician/clinical/allergies', request)
  return data.data
}

export async function updateAllergy(id: number, request: Partial<CreateAllergyRequest>): Promise<Allergy> {
  const { data } = await apiClient.put<{ success: boolean; data: Allergy }>(`/clinician/clinical/allergies/${id}`, request)
  return data.data
}

export async function deleteAllergy(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical/allergies/${id}`)
}
