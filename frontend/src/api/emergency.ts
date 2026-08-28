import { apiClient } from './client'

export type AcuityLevel = 'resuscitation' | 'emergent' | 'urgent' | 'non-urgent'
export type QueueStatus = 'waiting' | 'in_triage' | 'being_seen' | 'admitted' | 'discharged'
export type Disposition = 'admitted' | 'discharged' | 'transferred' | 'observation'
export type AmbulanceStatus = 'dispatched' | 'en_route' | 'on_scene' | 'transporting' | 'delivered'

export interface EmergencyVisit {
  id: number
  patient_id: number
  patient_name: string | null
  chief_complaint: string | null
  acuity_level: AcuityLevel
  queue_status: QueueStatus
  disposition: Disposition | null
  arrival_time: string | null
  seen_by_clinician_at: string | null
  departure_time: string | null
  length_of_stay_minutes: number
  clinician_name: string | null
  triage_nurse_name: string | null
  vitals_summary: string | null
  notes: string | null
}

export interface AmbulanceDispatch {
  id: number
  emergency_visit_id: number | null
  patient_name: string | null
  status: AmbulanceStatus
  pickup_location: string | null
  destination_hospital: string | null
  vehicle_id: string | null
  dispatched_at: string | null
  en_route_at: string | null
  on_scene_at: string | null
  transporting_at: string | null
  delivered_at: string | null
}

export interface EDDashboard {
  active_visits: number
  arrivals_today: number
  acuity_breakdown: Record<string, number>
  average_los_minutes: number
  crowding_ratio: number
  active_ambulances: AmbulanceDispatch[]
}

export async function checkInPatient(payload: { patient_id: number; chief_complaint?: string; vitals_summary?: string; notes?: string }): Promise<EmergencyVisit> {
  const { data } = await apiClient.post<{ success: boolean; data: EmergencyVisit }>('/nursing/ed/visits', payload)
  return data.data
}

export async function assignTriage(id: number, acuityLevel: AcuityLevel): Promise<EmergencyVisit> {
  const { data } = await apiClient.post<{ success: boolean; data: EmergencyVisit }>(`/nursing/ed/visits/${id}/triage`, { acuity_level: acuityLevel })
  return data.data
}

export async function assignClinician(id: number, clinicianId: number): Promise<EmergencyVisit> {
  const { data } = await apiClient.post<{ success: boolean; data: EmergencyVisit }>(`/nursing/ed/visits/${id}/assign`, { clinician_id: clinicianId })
  return data.data
}

export async function updateQueueStatus(id: number, queueStatus: QueueStatus): Promise<EmergencyVisit> {
  const { data } = await apiClient.post<{ success: boolean; data: EmergencyVisit }>(`/nursing/ed/visits/${id}/queue`, { queue_status: queueStatus })
  return data.data
}

export async function setDisposition(id: number, disposition: Disposition): Promise<EmergencyVisit> {
  const { data } = await apiClient.post<{ success: boolean; data: EmergencyVisit }>(`/nursing/ed/visits/${id}/disposition`, { disposition })
  return data.data
}

export async function getTrackBoard(): Promise<EmergencyVisit[]> {
  const { data } = await apiClient.get<{ success: boolean; data: EmergencyVisit[] }>('/nursing/ed/track-board')
  return data.data
}

export async function dispatchAmbulance(payload: { patient_id?: number; emergency_visit_id?: number; pickup_location?: string; destination_hospital?: string; vehicle_id?: string }): Promise<AmbulanceDispatch> {
  const { data } = await apiClient.post<{ success: boolean; data: AmbulanceDispatch }>('/nursing/ed/ambulance', payload)
  return data.data
}

export async function updateAmbulanceStatus(id: number, status: AmbulanceStatus): Promise<AmbulanceDispatch> {
  const { data } = await apiClient.post<{ success: boolean; data: AmbulanceDispatch }>(`/nursing/ed/ambulance/${id}/status`, { status })
  return data.data
}

export async function getEDDashboard(): Promise<EDDashboard> {
  const { data } = await apiClient.get<{ success: boolean; data: EDDashboard }>('/nursing/ed/dashboard')
  return data.data
}
