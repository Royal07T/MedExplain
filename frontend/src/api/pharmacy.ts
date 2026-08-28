import { apiClient } from './client'

export interface DrugInventory {
  id: number
  medication_id: number
  medication_name: string
  batch_number: string | null
  expiry_date: string
  quantity_on_hand: number
  minimum_stock_level: number
  maximum_stock_level: number
  location: string | null
  supplier: string | null
  unit_cost: number | null
  status: 'available' | 'reserved' | 'expired' | 'recalled'
  is_low_stock: boolean
  is_expired: boolean
  is_expiring_soon: boolean
  notes: string | null
}

export interface CreateDrugInventoryRequest {
  medication_id: number
  batch_number?: string
  expiry_date: string
  quantity_on_hand: number
  minimum_stock_level?: number
  maximum_stock_level?: number
  location?: string
  supplier?: string
  unit_cost?: number
  status?: DrugInventory['status']
  notes?: string
}

export interface Formulary {
  id: number
  medication_id: number
  medication_name: string
  formulary_code: string | null
  tier: 'generic' | 'preferred_brand' | 'non_preferred' | 'specialty'
  requires_prior_authorization: boolean
  quantity_limit: number | null
  days_supply_limit: number | null
  restrictions: string | null
  alternatives: string | null
  is_active: boolean
  is_currently_active: boolean
  effective_date: string | null
  discontinued_date: string | null
  notes: string | null
}

export interface CreateFormularyRequest {
  medication_id: number
  formulary_code?: string
  tier: Formulary['tier']
  requires_prior_authorization?: boolean
  quantity_limit?: number
  days_supply_limit?: number
  restrictions?: string
  alternatives?: string
  is_active?: boolean
  effective_date?: string
  notes?: string
}

// Drug Inventory
export async function getDrugInventory(params?: {
  status?: DrugInventory['status']
  low_stock?: boolean
  expiring_soon?: boolean
  expired?: boolean
}): Promise<DrugInventory[]> {
  const { data } = await apiClient.get<{ success: boolean; data: DrugInventory[] }>('/clinician/pharmacy/inventory', { params })
  return data.data
}

export async function createDrugInventory(request: CreateDrugInventoryRequest): Promise<DrugInventory> {
  const { data } = await apiClient.post<{ success: boolean; data: DrugInventory }>('/clinician/pharmacy/inventory', request)
  return data.data
}

export async function updateDrugInventory(id: number, request: Partial<CreateDrugInventoryRequest>): Promise<DrugInventory> {
  const { data } = await apiClient.put<{ success: boolean; data: DrugInventory }>(`/clinician/pharmacy/inventory/${id}`, request)
  return data.data
}

// Formulary
export async function getFormulary(params?: {
  active_only?: boolean
  tier?: Formulary['tier']
  requires_auth?: boolean
}): Promise<Formulary[]> {
  const { data } = await apiClient.get<{ success: boolean; data: Formulary[] }>('/clinician/pharmacy/formulary', { params })
  return data.data
}

export async function createFormulary(request: CreateFormularyRequest): Promise<Formulary> {
  const { data } = await apiClient.post<{ success: boolean; data: Formulary }>('/clinician/pharmacy/formulary', request)
  return data.data
}

export async function updateFormulary(id: number, request: Partial<CreateFormularyRequest>): Promise<Formulary> {
  const { data } = await apiClient.put<{ success: boolean; data: Formulary }>(`/clinician/pharmacy/formulary/${id}`, request)
  return data.data
}
