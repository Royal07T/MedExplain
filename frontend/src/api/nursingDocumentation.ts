import { apiClient } from './client'

export type CarePlanStatus = 'active' | 'on_hold' | 'completed' | 'cancelled'
export type MedAdminStatus = 'given' | 'refused' | 'held' | 'not_given'
export type AssessmentType = 'admission' | 'shift' | 'falls' | 'pressure_ulcer' | 'general'
export type FallRiskLevel = 'low' | 'moderate' | 'high'

export interface CarePlan {
  id: number
  patient_id: number
  patient_name: string | null
  title: string
  description: string | null
  goals: string[] | null
  interventions: string[] | null
  status: CarePlanStatus
  assignee_name: string | null
  creator_name: string | null
  started_at: string | null
  due_date: string | null
  completed_at: string | null
}

export interface MedicationAdministration {
  id: number
  patient_id: number
  patient_name: string | null
  prescription_id: number | null
  medication_name: string
  dose: string | null
  dose_unit: string | null
  route: string | null
  scheduled_time: string | null
  administered_time: string | null
  status: MedAdminStatus
  administered_by_name: string | null
  notes: string | null
  vitals_before: string | null
}

export interface NursingAssessment {
  id: number
  patient_id: number
  patient_name: string | null
  assessment_type: AssessmentType
  template_name: string | null
  assessment_data: Record<string, unknown> | null
  findings: string | null
  notes: string | null
  assessment_time: string | null
  performed_by_name: string | null
  fall_risk_score: number | null
  fall_risk_level: FallRiskLevel | null
  pressure_ulcer_stage: string | null
}

export interface ShiftHandoff {
  id: number
  patient_id: number
  patient_name: string | null
  from_nurse_name: string | null
  to_nurse_name: string | null
  unit: string | null
  clinical_summary: string | null
  tasks_to_complete: string | null
  medication_review: string | null
  safety_concerns: string | null
  is_complete: boolean
  handoff_time: string | null
}

export interface AssessmentTemplate {
  value: AssessmentType
  label: string
}

export async function getCarePlans(params?: { patient_id?: number; status?: CarePlanStatus }): Promise<CarePlan[]> {
  const { data } = await apiClient.get<{ success: boolean; data: CarePlan[] }>('/nursing/care-plans', { params })
  return data.data
}

export async function createCarePlan(payload: { patient_id: number; title: string; description?: string; goals?: string[]; interventions?: string[]; assigned_to?: number; due_date?: string }): Promise<CarePlan> {
  const { data } = await apiClient.post<{ success: boolean; data: CarePlan }>('/nursing/care-plans', payload)
  return data.data
}

export async function updateCarePlan(id: number, payload: { title?: string; description?: string; goals?: string[]; interventions?: string[]; assigned_to?: number; due_date?: string }): Promise<CarePlan> {
  const { data } = await apiClient.put<{ success: boolean; data: CarePlan }>(`/nursing/care-plans/${id}`, payload)
  return data.data
}

export async function updateCarePlanStatus(id: number, status: CarePlanStatus): Promise<CarePlan> {
  const { data } = await apiClient.post<{ success: boolean; data: CarePlan }>(`/nursing/care-plans/${id}/status`, { status })
  return data.data
}

export async function getMAR(params?: { patient_id?: number }): Promise<MedicationAdministration[]> {
  const { data } = await apiClient.get<{ success: boolean; data: MedicationAdministration[] }>('/nursing/mar', { params })
  return data.data
}

export async function createMAR(payload: { patient_id: number; medication_name: string; dose?: string; dose_unit?: string; route?: string; scheduled_time?: string; status?: MedAdminStatus; notes?: string; vitals_before?: string }): Promise<MedicationAdministration> {
  const { data } = await apiClient.post<{ success: boolean; data: MedicationAdministration }>('/nursing/mar', payload)
  return data.data
}

export async function updateMARStatus(id: number, status: MedAdminStatus, notes?: string): Promise<MedicationAdministration> {
  const { data } = await apiClient.post<{ success: boolean; data: MedicationAdministration }>(`/nursing/mar/${id}/status`, { status, notes })
  return data.data
}

export async function getAssessmentTemplates(): Promise<AssessmentTemplate[]> {
  const { data } = await apiClient.get<{ success: boolean; data: AssessmentTemplate[] }>('/nursing/assessment-templates')
  return data.data
}

export async function getAssessments(params?: { patient_id?: number; type?: AssessmentType }): Promise<NursingAssessment[]> {
  const { data } = await apiClient.get<{ success: boolean; data: NursingAssessment[] }>('/nursing/assessments', { params })
  return data.data
}

export async function createAssessment(payload: { patient_id: number; assessment_type: AssessmentType; template_name?: string; assessment_data?: Record<string, unknown>; findings?: string; notes?: string; fall_risk_score?: number; fall_risk_level?: FallRiskLevel; pressure_ulcer_stage?: string; assessment_time?: string }): Promise<NursingAssessment> {
  const { data } = await apiClient.post<{ success: boolean; data: NursingAssessment }>('/nursing/assessments', payload)
  return data.data
}

export async function getFallRiskSummary(): Promise<NursingAssessment[]> {
  const { data } = await apiClient.get<{ success: boolean; data: NursingAssessment[] }>('/nursing/fall-risk')
  return data.data
}

export async function getHandoffs(params?: { patient_id?: number }): Promise<ShiftHandoff[]> {
  const { data } = await apiClient.get<{ success: boolean; data: ShiftHandoff[] }>('/nursing/handoffs', { params })
  return data.data
}

export async function createHandoff(payload: { patient_id: number; to_nurse_id?: number; unit?: string; clinical_summary?: string; tasks_to_complete?: string; medication_review?: string; safety_concerns?: string }): Promise<ShiftHandoff> {
  const { data } = await apiClient.post<{ success: boolean; data: ShiftHandoff }>('/nursing/handoffs', payload)
  return data.data
}
