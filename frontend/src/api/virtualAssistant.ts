import { apiClient } from './client'

export type SymptomUrgency = 'emergency' | 'urgent' | 'moderate' | 'general'

export interface SymptomHit {
  symptom: string
  category: string
  urgent: boolean
}

export interface SymptomCheckResult {
  urgency: SymptomUrgency
  message: string
  red_flags: string[]
  matched: SymptomHit[]
  disclaimer: string
}

export interface MedicationReminder {
  id: number
  medication_name: string
  dose: string | null
  route: string | null
  frequency: string | null
  scheduled_time: string | null
  notes: string | null
  active: boolean
  last_taken_at: string | null
  created_at: string
}

export interface CreateMedicationReminderRequest {
  medication_name: string
  dose?: string
  route?: string
  frequency?: string
  scheduled_time?: string
  notes?: string
}

export async function checkSymptoms(text: string): Promise<SymptomCheckResult> {
  const { data } = await apiClient.post<{ success: boolean; data: SymptomCheckResult }>(
    '/assistant/symptom-check',
    { text },
  )
  return data.data
}

export async function getMedicationReminders(): Promise<MedicationReminder[]> {
  const { data } = await apiClient.get<{ success: boolean; data: MedicationReminder[] }>(
    '/patient/medication-reminders',
  )
  return data.data
}

export async function createMedicationReminder(
  request: CreateMedicationReminderRequest,
): Promise<MedicationReminder> {
  const { data } = await apiClient.post<{ success: boolean; data: MedicationReminder }>(
    '/patient/medication-reminders',
    request,
  )
  return data.data
}

export async function markMedicationReminderTaken(id: number): Promise<MedicationReminder> {
  const { data } = await apiClient.post<{ success: boolean; data: MedicationReminder }>(
    `/patient/medication-reminders/${id}/taken`,
  )
  return data.data
}

export async function toggleMedicationReminder(id: number): Promise<MedicationReminder> {
  const { data } = await apiClient.post<{ success: boolean; data: MedicationReminder }>(
    `/patient/medication-reminders/${id}/toggle`,
  )
  return data.data
}

export async function deleteMedicationReminder(id: number): Promise<void> {
  await apiClient.delete<{ success: boolean }>(`/patient/medication-reminders/${id}`)
}
