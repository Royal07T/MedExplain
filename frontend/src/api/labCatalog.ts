import { apiClient } from './client'

export interface LabTestCatalog {
  id: number
  test_code: string
  test_name: string
  description: string | null
  category: string | null
  specimen_type: string
  container_type: string | null
  turnaround_hours: number
  cost: number | null
  reference_ranges: any[] | null
  critical_values: any[] | null
  is_active: boolean
  notes: string | null
}

export interface CreateLabTestCatalogRequest {
  test_code: string
  test_name: string
  description?: string
  category?: string
  specimen_type?: string
  container_type?: string
  turnaround_hours?: number
  cost?: number
  reference_ranges?: any[]
  critical_values?: any[]
  is_active?: boolean
  notes?: string
}

export async function getLabTestCatalog(params?: {
  active_only?: boolean
  category?: string
  search?: string
}): Promise<LabTestCatalog[]> {
  const { data } = await apiClient.get<{ success: boolean; data: LabTestCatalog[] }>('/clinician/lab/test-catalog', { params })
  return data.data
}

export async function createLabTestCatalog(request: CreateLabTestCatalogRequest): Promise<LabTestCatalog> {
  const { data } = await apiClient.post<{ success: boolean; data: LabTestCatalog }>('/clinician/lab/test-catalog', request)
  return data.data
}

export async function updateLabTestCatalog(id: number, request: Partial<CreateLabTestCatalogRequest>): Promise<LabTestCatalog> {
  const { data } = await apiClient.put<{ success: boolean; data: LabTestCatalog }>(`/clinician/lab/test-catalog/${id}`, request)
  return data.data
}

export async function deleteLabTestCatalog(id: number): Promise<void> {
  await apiClient.delete(`/clinician/lab/test-catalog/${id}`)
}
