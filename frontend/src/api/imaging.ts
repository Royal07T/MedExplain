import { apiClient } from './client'

export type ImagingModality = 'xray' | 'ct' | 'mri' | 'ultrasound' | 'nuclear_medicine' | 'pet_scan' | 'fluoroscopy'
export type ImagingPriority = 'routine' | 'urgent' | 'stat'
export type ImagingOrderStatus = 'pending' | 'scheduled' | 'in_progress' | 'completed' | 'cancelled'

export interface RadiologyReport {
  id: number
  findings: string | null
  impression: string | null
  report_text: string | null
  status: string | null
  reported_at: string | null
}

export interface ImagingOrder {
  id: number
  modality: ImagingModality
  body_region: string | null
  clinical_indication: string | null
  priority: ImagingPriority
  status: ImagingOrderStatus
  icd_code: string | null
  ordered_at: string | null
  scheduled_at: string | null
  completed_at: string | null
  radiation_dose_mgy: number | null
  image_count: number | null
  notes: string | null
  clinician_name: string | null
  user_name: string | null
  report: RadiologyReport | null
}

export interface CreateImagingOrderRequest {
  patient_id: number
  modality: ImagingModality
  body_region?: string
  clinical_indication?: string
  priority?: ImagingPriority
  icd_code?: string
  scheduled_at?: string
  notes?: string
}

export async function getPatientImagingOrders(patientId: number): Promise<ImagingOrder[]> {
  const { data } = await apiClient.get<{ success: boolean; data: ImagingOrder[] }>(`/clinician/imaging/patients/${patientId}/orders`)
  return data.data
}

export async function createImagingOrder(request: CreateImagingOrderRequest): Promise<ImagingOrder> {
  const { data } = await apiClient.post<{ success: boolean; data: ImagingOrder }>('/clinician/imaging/orders', request)
  return data.data
}

export async function getImagingOrder(id: number): Promise<ImagingOrder> {
  const { data } = await apiClient.get<{ success: boolean; data: ImagingOrder }>(`/clinician/imaging/orders/${id}`)
  return data.data
}

export async function updateImagingOrderStatus(id: number, status: ImagingOrderStatus): Promise<ImagingOrder> {
  const { data } = await apiClient.post<{ success: boolean; data: ImagingOrder }>(`/clinician/imaging/orders/${id}/status`, { status })
  return data.data
}

export async function recordImagingResult(id: number, payload: { radiation_dose_mgy?: number; image_count?: number; findings?: string }): Promise<ImagingOrder> {
  const { data } = await apiClient.post<{ success: boolean; data: ImagingOrder }>(`/clinician/imaging/orders/${id}/result`, payload)
  return data.data
}

export async function cancelImagingOrder(id: number): Promise<ImagingOrder> {
  const { data } = await apiClient.post<{ success: boolean; data: ImagingOrder }>(`/clinician/imaging/orders/${id}/cancel`)
  return data.data
}

export async function saveRadiologyReport(
  id: number,
  payload: { findings?: string; impression?: string; report_text?: string; status?: 'draft' | 'final' },
): Promise<{ id: number; imaging_order_id: number; findings: string | null; impression: string | null; report_text: string | null; status: string | null; reported_at: string | null }> {
  const { data } = await apiClient.post<{ success: boolean; data: { id: number; imaging_order_id: number; findings: string | null; impression: string | null; report_text: string | null; status: string | null; reported_at: string | null } }>(`/clinician/imaging/orders/${id}/report`, payload)
  return data.data
}
