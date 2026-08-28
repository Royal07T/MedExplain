import { apiClient } from './client'

export type CleaningStatus = 'clean' | 'needs_cleaning' | 'being_cleaned' | 'occupied'

export interface Ward {
  id: number
  name: string
  code: string
  floor: string | null
  location: string | null
  capacity: number | null
  beds_count: number
  occupied_beds_count: number
  is_active: boolean
}

export interface BedAssignmentInfo {
  id: number
  name: string | null
  assigned_at: string | null
}

export interface Bed {
  id: number
  ward_id: number
  bed_number: number
  bed_type: string
  is_occupied: boolean
  cleaning_status: CleaningStatus
  notes: string | null
  current_patient: BedAssignmentInfo | null
}

export interface BedUtilization {
  total_beds: number
  occupied_beds: number
  available_beds: number
  utilization_rate: number
  wards: Array<{
    id: number
    name: string
    beds_count: number
    occupied_beds_count: number
    utilization_rate: number
  }>
}

export async function getWards(): Promise<Ward[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Ward[] }>('/nursing/wards')
  return data.data
}

export async function createWard(payload: { name: string; code: string; floor?: string | null; location?: string | null; capacity?: number | null; department_id?: number }): Promise<Ward> {
  const { data } = await apiClient.post<{ success: boolean; data: Ward }>('/nursing/wards', payload)
  return data.data
}

export async function getWardBeds(wardId: number): Promise<Bed[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Bed[] }>(`/nursing/wards/${wardId}/beds`)
  return data.data
}

export async function addWardBeds(wardId: number, count: number, bedType = 'standard'): Promise<{ created_count: number; first_bed_number: number }> {
  const { data } = await apiClient.post<{ success: boolean; data: { created_count: number; first_bed_number: number } }>(`/nursing/wards/${wardId}/beds`, { count, bed_type: bedType })
  return data.data
}

export async function assignPatientToBed(bedId: number, patientId: number): Promise<Bed> {
  const { data } = await apiClient.post<{ success: boolean; data: Bed }>(`/nursing/beds/${bedId}/assign`, { patient_id: patientId })
  return data.data
}

export async function dischargeBed(bedId: number): Promise<Bed> {
  const { data } = await apiClient.post<{ success: boolean; data: Bed }>(`/nursing/beds/${bedId}/discharge`)
  return data.data
}

export async function updateBedCleaning(bedId: number, cleaningStatus: CleaningStatus): Promise<Bed> {
  const { data } = await apiClient.post<{ success: boolean; data: Bed }>(`/nursing/beds/${bedId}/cleaning`, { cleaning_status: cleaningStatus })
  return data.data
}

export async function getBedUtilization(): Promise<BedUtilization> {
  const { data } = await apiClient.get<{ success: boolean; data: BedUtilization }>('/nursing/utilization')
  return data.data
}
