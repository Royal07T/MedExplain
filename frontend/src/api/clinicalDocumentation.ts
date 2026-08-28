import { apiClient } from './client'

export interface ProblemList {
  id: number
  patient_id: number
  organization_id: number
  icd10_code: string
  icd10_description: string
  clinical_notes: string | null
  status: 'active' | 'resolved' | 'chronic' | 'recurrent'
  onset_date: string | null
  resolved_date: string | null
  created_by: number
  updated_by: number | null
  created_at: string
  updated_at: string
}

export interface Allergy {
  id: number
  patient_id: number
  organization_id: number
  allergen_type: 'drug' | 'food' | 'environmental' | 'other'
  allergen_name: string
  reaction_description: string | null
  severity: 'mild' | 'moderate' | 'severe' | 'life_threatening'
  status: 'active' | 'resolved' | 'unconfirmed'
  onset_date: string | null
  notes: string | null
  created_by: number
  updated_by: number | null
  created_at: string
  updated_at: string
}

export interface VitalSign {
  id: number
  patient_id: number
  encounter_id: number | null
  organization_id: number
  temperature: number | null
  temperature_unit: string
  heart_rate: number | null
  blood_pressure_systolic: number | null
  blood_pressure_diastolic: number | null
  respiratory_rate: number | null
  oxygen_saturation: number | null
  weight: number | null
  weight_unit: string
  height: number | null
  height_unit: string
  bmi: number | null
  pain_score: number | null
  notes: string | null
  recorded_by: number
  recorded_at: string
  created_at: string
  updated_at: string
}

export type NoteType = 'admission' | 'progress' | 'discharge' | 'consultation' | 'procedure' | 'other'

export interface ClinicalNote {
  id: number
  patient_id: number
  encounter_id: number | null
  organization_id: number
  template_id: number | null
  note_type: NoteType
  subjective: string | null
  objective: string | null
  assessment: string | null
  plan: string | null
  full_note: string | null
  author_id: number
  cosigner_id: number | null
  cosigned_at: string | null
  status: 'draft' | 'final' | 'amended'
  created_at: string
  updated_at: string
}

export interface ClinicalNoteTemplate {
  id: number
  organization_id: number
  name: string
  specialty: string | null
  note_type: NoteType
  structure: Record<string, any> | null
  default_subjective: string | null
  default_objective: string | null
  default_assessment: string | null
  default_plan: string | null
  is_active: boolean
  created_by: number
  updated_by: number | null
  created_at: string
  updated_at: string
}

// Problem List
export async function getProblemList(patientId: number): Promise<ProblemList[]> {
  const response = await apiClient.get(`/clinician/clinical/patients/${patientId}/problems`)
  return response.data
}

export async function createProblem(data: Partial<ProblemList>): Promise<ProblemList> {
  const response = await apiClient.post('/clinician/clinical/problems', data)
  return response.data
}

export async function updateProblem(id: number, data: Partial<ProblemList>): Promise<ProblemList> {
  const response = await apiClient.put(`/clinician/clinical/problems/${id}`, data)
  return response.data
}

export async function deleteProblem(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical/problems/${id}`)
}

// Allergies
export async function getAllergies(patientId: number): Promise<Allergy[]> {
  const response = await apiClient.get(`/clinician/clinical/patients/${patientId}/allergies`)
  return response.data
}

export async function createAllergy(data: Partial<Allergy>): Promise<Allergy> {
  const response = await apiClient.post('/clinician/clinical/allergies', data)
  return response.data
}

export async function updateAllergy(id: number, data: Partial<Allergy>): Promise<Allergy> {
  const response = await apiClient.put(`/clinician/clinical/allergies/${id}`, data)
  return response.data
}

export async function deleteAllergy(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical/allergies/${id}`)
}

// Vital Signs
export async function getVitalSigns(patientId: number): Promise<VitalSign[]> {
  const response = await apiClient.get(`/clinician/clinical/patients/${patientId}/vital-signs`)
  return response.data
}

export async function getVitalSignTrends(patientId: number, days: number = 30): Promise<any> {
  const response = await apiClient.get(`/clinician/clinical/patients/${patientId}/vital-signs/trends`, {
    params: { days }
  })
  return response.data
}

export async function createVitalSign(data: Partial<VitalSign>): Promise<VitalSign> {
  const response = await apiClient.post('/clinician/clinical/vital-signs', data)
  return response.data
}

// Clinical Notes
export async function getClinicalNotes(patientId: number): Promise<ClinicalNote[]> {
  const response = await apiClient.get(`/clinician/clinical/patients/${patientId}/clinical-notes`)
  return response.data
}

export async function createClinicalNote(data: Partial<ClinicalNote>): Promise<ClinicalNote> {
  const response = await apiClient.post('/clinician/clinical/clinical-notes', data)
  return response.data
}

export async function updateClinicalNote(id: number, data: Partial<ClinicalNote>): Promise<ClinicalNote> {
  const response = await apiClient.put(`/clinician/clinical/clinical-notes/${id}`, data)
  return response.data
}

export async function cosignClinicalNote(id: number): Promise<ClinicalNote> {
  const response = await apiClient.post(`/clinician/clinical/clinical-notes/${id}/cosign`)
  return response.data
}

export async function deleteClinicalNote(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical/clinical-notes/${id}`)
}

// Clinical Note Templates
export async function getTemplates(): Promise<ClinicalNoteTemplate[]> {
  const response = await apiClient.get('/clinician/clinical/templates')
  return response.data
}

export async function createTemplate(data: Partial<ClinicalNoteTemplate>): Promise<ClinicalNoteTemplate> {
  const response = await apiClient.post('/clinician/clinical/templates', data)
  return response.data
}

export async function updateTemplate(id: number, data: Partial<ClinicalNoteTemplate>): Promise<ClinicalNoteTemplate> {
  const response = await apiClient.put(`/clinician/clinical/templates/${id}`, data)
  return response.data
}

export async function deleteTemplate(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical/templates/${id}`)
}
