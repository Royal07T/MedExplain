import { apiClient } from './client'

export interface CDSAlert {
  type: 'allergy' | 'drug_interaction' | 'dose_adjustment' | 'vital_sign' | 'guideline' | 'preventive'
  severity: 'mild' | 'moderate' | 'severe'
  message: string
  [key: string]: any
}

export interface CDSResponse {
  alerts: CDSAlert[]
  count: number
  summary?: {
    severe: number
    moderate: number
    mild: number
  }
}

export async function checkDrugAllergy(patientId: number, medications: string[]): Promise<CDSResponse> {
  const response = await apiClient.post('/clinician/clinical/cds/check-drug-allergy', {
    patient_id: patientId,
    medications
  })
  return response.data
}

export async function checkDrugInteractions(medications: string[]): Promise<CDSResponse> {
  const response = await apiClient.post('/clinician/clinical/cds/check-drug-interactions', {
    medications
  })
  return response.data
}

export async function checkDoseAdjustments(patientId: number, medications: Array<{ name: string }>): Promise<CDSResponse> {
  const response = await apiClient.post('/clinician/clinical/cds/check-dose-adjustments', {
    patient_id: patientId,
    medications
  })
  return response.data
}

export async function checkVitalSigns(patientId: number): Promise<CDSResponse> {
  const response = await apiClient.post('/clinician/clinical/cds/check-vital-signs', {
    patient_id: patientId
  })
  return response.data
}

export async function getGuidelineReminders(patientId: number): Promise<CDSResponse> {
  const response = await apiClient.get(`/clinician/clinical/cds/guidelines/${patientId}`)
  return response.data
}

export async function getPreventiveCareReminders(patientId: number, age: number): Promise<CDSResponse> {
  const response = await apiClient.get(`/clinician/clinical/cds/preventive/${patientId}`, {
    params: { age }
  })
  return response.data
}

export async function comprehensiveCDSCheck(patientId: number, options?: {
  medications?: string[]
  age?: number
}): Promise<CDSResponse> {
  const response = await apiClient.post('/clinician/clinical/cds/comprehensive', {
    patient_id: patientId,
    ...options
  })
  return response.data
}
