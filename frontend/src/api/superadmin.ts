import { apiClient } from './client'

export interface SuperAdminOrganization {
  id: number
  name: string
  slug: string
  address: string | null
  phone: string | null
  email: string | null
  website: string | null
  is_active: boolean
  users_count: number
  patients_count: number
  created_at: string
}

export interface SuperAdminUser {
  id: number
  name: string
  email: string
  role: string
  organization_id: number | null
  profile: {
    first_name: string | null
    last_name: string | null
    avatar_url: string | null
  } | null
  created_at: string
}

export interface SuperAdminAIUsage {
  daily: Array<{
    date: string
    queries: number
    cost: number
    avg_latency: number
  }>
  totals: {
    queries: number
    cost: number
    avg_latency: number
  }
}

export interface SuperAdminSystemHealth {
  system: {
    uptime: string
    response_time: string
    error_rate: string
    php_version: string
    laravel_version: string
    database_size_mb: number
  }
  users: {
    total: number
    verified: number
    unverified: number
  }
  organizations: Array<{
    id: number
    name: string
    users_count: number
    patients_count: number
  }>
  recent_activity: {
    new_users_today: number
    active_sessions: number
  }
}

export async function fetchSuperAdminOrganizations(): Promise<SuperAdminOrganization[]> {
  const { data } = await apiClient.get<{ data: SuperAdminOrganization[] }>('/superadmin/organizations')
  return data.data
}

export async function createSuperAdminOrganization(payload: { name: string; slug: string; address?: string; phone?: string; email?: string; website?: string }): Promise<SuperAdminOrganization> {
  const { data } = await apiClient.post<{ data: SuperAdminOrganization }>('/superadmin/organizations', payload)
  return data.data
}

export async function fetchSuperAdminUsers(params?: { role?: string; organization_id?: number; search?: string }): Promise<{ data: SuperAdminUser[]; current_page: number; last_page: number; total: number }> {
  const { data } = await apiClient.get('/superadmin/users', { params })
  return data
}

export async function updateSuperAdminUser(id: number, payload: { name?: string; email?: string; role?: string; organization_id?: number | null }): Promise<SuperAdminUser> {
  const { data } = await apiClient.put<{ data: SuperAdminUser }>(`/superadmin/users/${id}`, payload)
  return data.data
}

export async function fetchSuperAdminAIUsage(): Promise<SuperAdminAIUsage> {
  const { data } = await apiClient.get<{ data: SuperAdminAIUsage }>('/superadmin/ai/usage')
  return data.data
}

export async function fetchSuperAdminSystemHealth(): Promise<SuperAdminSystemHealth> {
  const { data } = await apiClient.get<{ data: SuperAdminSystemHealth }>('/superadmin/system/health')
  return data.data
}
