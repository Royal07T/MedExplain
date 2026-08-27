import { apiClient } from './client'

export interface AdminDepartment {
  id: number
  organization_id: number
  name: string
  code: string
  description: string | null
  capacity: number | null
  clinicians_count: number
  created_at: string
  updated_at: string
}

export interface AdminStaffMember {
  id: number
  name: string
  email: string
  role: string
  profile: {
    first_name: string | null
    last_name: string | null
    avatar_url: string | null
  } | null
  departments_count: number
  created_at: string
}

export interface InventoryItem {
  id: number
  organization_id: number
  name: string
  sku: string
  item_type: string | null
  status: string
  quantity_on_hand: number
  minimum_stock_level: number | null
  maximum_stock_level: number | null
  batch_number: string | null
  expiration_date: string | null
  supplier: string | null
  created_at: string
}

export interface AdminInventoryData {
  items: InventoryItem[]
  summary: {
    total_items: number
    low_stock: number
    out_of_stock: number
  }
}

export interface AdminInvoice {
  id: number
  patient_id: number
  organization_id: number
  appointment_id: number | null
  invoice_number: string
  amount: number
  paid_amount: number
  status: string
  payment_method: string | null
  notes: string | null
  issued_at: string | null
  due_at: string | null
  paid_at: string | null
  patient?: {
    id: number
    first_name: string
    last_name: string
    mrn: string
  }
  created_at: string
}

export interface AdminBillingData {
  invoices: {
    data: AdminInvoice[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  summary: {
    total_revenue: number
    outstanding: number
    pending_count: number
    paid_count: number
  }
}

export async function fetchAdminDepartments(): Promise<AdminDepartment[]> {
  const { data } = await apiClient.get<{ data: AdminDepartment[] }>('/admin/departments')
  return data.data
}

export async function createAdminDepartment(payload: { name: string; code: string; description?: string; capacity?: number }): Promise<AdminDepartment> {
  const { data } = await apiClient.post<{ data: AdminDepartment }>('/admin/departments', payload)
  return data.data
}

export async function deleteAdminDepartment(id: number): Promise<void> {
  await apiClient.delete(`/admin/departments/${id}`)
}

export async function fetchAdminStaff(): Promise<AdminStaffMember[]> {
  const { data } = await apiClient.get<{ data: AdminStaffMember[] }>('/admin/staff')
  return data.data
}

export async function assignStaffToDepartment(userId: number, departmentId: number): Promise<void> {
  await apiClient.post(`/admin/staff/${userId}/departments/${departmentId}`)
}

export async function removeStaffFromDepartment(userId: number, departmentId: number): Promise<void> {
  await apiClient.delete(`/admin/staff/${userId}/departments/${departmentId}`)
}

export async function fetchAdminInventory(): Promise<AdminInventoryData> {
  const { data } = await apiClient.get<{ data: AdminInventoryData }>('/admin/inventory')
  return data.data
}

export async function createInventoryItem(payload: { name: string; sku: string; item_type?: string; quantity_on_hand: number; minimum_stock_level?: number; maximum_stock_level?: number; batch_number?: string; expiration_date?: string; supplier?: string }): Promise<InventoryItem> {
  const { data } = await apiClient.post<{ data: InventoryItem }>('/admin/inventory', payload)
  return data.data
}

export async function deleteInventoryItem(id: number): Promise<void> {
  await apiClient.delete(`/admin/inventory/${id}`)
}

export async function fetchAdminBilling(): Promise<AdminBillingData> {
  const { data } = await apiClient.get<{ data: AdminBillingData }>('/admin/billing')
  return data.data
}

export async function createAdminInvoice(payload: { patient_id: number; amount: number; payment_method?: string; notes?: string }): Promise<AdminInvoice> {
  const { data } = await apiClient.post<{ data: AdminInvoice }>('/admin/billing', payload)
  return data.data
}
