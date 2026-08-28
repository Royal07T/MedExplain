import { apiClient } from './client'

export interface Appointment {
  id: number
  patient_id: number
  clinician_id: number
  organization_id: number
  status: 'scheduled' | 'checked_in' | 'in_progress' | 'completed' | 'cancelled' | 'no_show'
  acuity_level: 'resuscitation' | 'emergent' | 'urgent' | 'non-urgent'
  chief_complaint: string | null
  symptoms: string | null
  scheduled_at: string | null
  check_in_time: string | null
  check_out_time: string | null
  duration_minutes: number | null
}

export interface CreateAppointmentRequest {
  patient_id: number
  clinician_id: number
  status: 'scheduled' | 'checked_in' | 'in_progress' | 'completed' | 'cancelled' | 'no_show'
  acuity_level: 'resuscitation' | 'emergent' | 'urgent' | 'non-urgent'
  chief_complaint?: string
  symptoms?: string
  scheduled_at: string
  duration_minutes?: number
}

export async function getAppointments(patientId: number): Promise<Appointment[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Appointment[] }>(`/clinician/patients/${patientId}/appointments`)
  return data.data
}

export async function createAppointment(request: CreateAppointmentRequest): Promise<Appointment> {
  const { data } = await apiClient.post<{ success: boolean; data: Appointment }>('/clinician/appointments', request)
  return data.data
}

export async function getAppointment(id: number): Promise<Appointment> {
  const { data } = await apiClient.get<{ success: boolean; data: Appointment }>(`/clinician/appointments/${id}`)
  return data.data
}

export async function updateAppointmentStatus(id: number, status: Appointment['status']): Promise<Appointment> {
  const { data } = await apiClient.put<{ success: boolean; data: Appointment }>(`/clinician/appointments/${id}/status`, { status })
  return data.data
}

export async function checkInAppointment(id: number): Promise<Appointment> {
  const { data } = await apiClient.post<{ success: boolean; data: Appointment }>(`/clinician/appointments/${id}/check-in`)
  return data.data
}

// Patient-specific functions
export async function getMyAppointments(): Promise<Appointment[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Appointment[] }>('/patient/appointments')
  return data.data
}

export async function bookAppointment(request: Omit<CreateAppointmentRequest, 'patient_id' | 'clinician_id' | 'status' | 'acuity_level'> & { clinician_id: number }): Promise<Appointment> {
  const { data } = await apiClient.post<{ success: boolean; data: Appointment }>('/patient/appointments', request)
  return data.data
}

export async function cancelMyAppointment(id: number): Promise<Appointment> {
  const { data } = await apiClient.delete<{ success: boolean; data: Appointment }>(`/patient/appointments/${id}`)
  return data.data
}

export interface ClinicianInfo {
  id: number
  name: string
  email: string
}

export async function getMyClinicians(): Promise<ClinicianInfo[]> {
  const { data } = await apiClient.get<{ success: boolean; data: ClinicianInfo[] }>('/patient/clinicians')
  return data.data
}

export async function getAvailableClinicians(): Promise<ClinicianInfo[]> {
  const { data } = await apiClient.get<{ success: boolean; data: ClinicianInfo[] }>('/patient/available-clinicians')
  return data.data
}
