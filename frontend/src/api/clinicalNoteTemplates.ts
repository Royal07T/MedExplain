import { apiClient } from './client'

export interface ClinicalNoteTemplate {
  id: number
  name: string
  specialty: string
  note_type: 'admission' | 'progress' | 'discharge' | 'consultation' | 'procedure' | 'other'
  structure: Record<string, any>
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

export interface CreateClinicalNoteTemplateRequest {
  name: string
  specialty: string
  note_type: ClinicalNoteTemplate['note_type']
  structure?: Record<string, any>
  default_subjective?: string
  default_objective?: string
  default_assessment?: string
  default_plan?: string
  is_active?: boolean
}

export async function getClinicalNoteTemplates(params?: {
  specialty?: string
  note_type?: string
  active_only?: boolean
}): Promise<ClinicalNoteTemplate[]> {
  const { data } = await apiClient.get<{ success: boolean; data: ClinicalNoteTemplate[] }>('/clinician/clinical-note-templates', { params })
  return data.data
}

export async function getClinicalNoteTemplate(id: number): Promise<ClinicalNoteTemplate> {
  const { data } = await apiClient.get<{ success: boolean; data: ClinicalNoteTemplate }>(`/clinician/clinical-note-templates/${id}`)
  return data.data
}

export async function createClinicalNoteTemplate(request: CreateClinicalNoteTemplateRequest): Promise<ClinicalNoteTemplate> {
  const { data } = await apiClient.post<{ success: boolean; data: ClinicalNoteTemplate }>('/clinician/clinical-note-templates', request)
  return data.data
}

export async function updateClinicalNoteTemplate(id: number, request: Partial<CreateClinicalNoteTemplateRequest>): Promise<ClinicalNoteTemplate> {
  const { data } = await apiClient.put<{ success: boolean; data: ClinicalNoteTemplate }>(`/clinician/clinical-note-templates/${id}`, request)
  return data.data
}

export async function deleteClinicalNoteTemplate(id: number): Promise<void> {
  await apiClient.delete(`/clinician/clinical-note-templates/${id}`)
}
