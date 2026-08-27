import { apiClient } from './client'

export interface PatientDashboardData {
  upcoming_appointments: Array<{
    id: number
    check_in_time: string
    clinician_name?: string
    type?: string
    status: string
  }>
  recent_labs: Array<{
    id: number
    test_name: string
    result: string
    unit: string | null
    status: string
    collected_at: string
  }>
  medications: Array<{
    id: number
    name: string
    strength: string | null
    dose: string | null
    frequency: string | null
    status: string
  }>
  recent_documents: Array<{
    id: number
    original_filename: string
    document_type: string
    status: string
    created_at: string
  }>
  health_summary: {
    total_labs: number
    active_medications: number
    recent_documents: number
  }
}

export interface ClinicianDashboardData {
  today_appointments: Array<{
    id: number
    patient_name?: string
    check_in_time: string
    status: string
    type?: string
  }>
  waiting_patients: Array<{
    id: number
    patient_name?: string
    check_in_time: string
    status: string
  }>
  recent_encounters: Array<{
    id: number
    patient_name?: string
    chief_complaint?: string
    check_in_time: string
    check_out_time: string | null
    queue_status: string
  }>
  pending_labs: Array<{
    id: number
    test_name: string
    status: string
    created_at: string
  }>
  patients_requiring_attention: unknown[]
  stats: {
    patients_today: number
    encounters_completed: number
    pending_reviews: number
  }
}

export interface NursingDashboardData {
  assigned_patients: Array<{
    id: number
    first_name: string
    last_name: string
    mrn: string
  }>
  pending_vitals: Array<{
    id: number
    first_name: string
    last_name: string
    mrn: string
  }>
  medication_rounds: Array<{
    id: number
    name: string
    dose: string | null
    frequency: string | null
    status: string
  }>
  nursing_tasks: unknown[]
  active_alerts: unknown[]
  admissions_discharges: Array<{
    id: number
    patient_name?: string
    check_in_time: string
    queue_status: string
  }>
}

export interface AdminDashboardData {
  patient_count: {
    total: number
    new_today: number
  }
  appointments: {
    scheduled: number
    completed: number
    no_shows: number
  }
  admissions: {
    today: number
    this_week: number
  }
  staff: {
    on_duty: number
    available: number
  }
  laboratory: {
    ordered: number
    completed: number
    pending: number
  }
  pharmacy: {
    filled: number
    pending: number
  }
  billing: {
    revenue: number
    outstanding: number
  }
}

export interface SuperAdminDashboardData {
  platform_overview: {
    organizations: number
    total_users: number
    active_sessions: number
  }
  ai_usage: {
    queries_today: number
    cost_today: number
    avg_latency: number
  }
  system_health: {
    uptime: string
    response_time: string
    error_rate: string
  }
}

export async function fetchPatientDashboard(): Promise<PatientDashboardData> {
  const { data } = await apiClient.get<{ data: PatientDashboardData }>('/patient/dashboard')
  return data.data
}

export async function fetchClinicianDashboard(): Promise<ClinicianDashboardData> {
  const { data } = await apiClient.get<{ data: ClinicianDashboardData }>('/clinician/dashboard')
  return data.data
}

export async function fetchNursingDashboard(): Promise<NursingDashboardData> {
  const { data } = await apiClient.get<{ data: NursingDashboardData }>('/nursing/dashboard')
  return data.data
}

export async function fetchAdminDashboard(): Promise<AdminDashboardData> {
  const { data } = await apiClient.get<{ data: AdminDashboardData }>('/admin/dashboard')
  return data.data
}

export async function fetchSuperAdminDashboard(): Promise<SuperAdminDashboardData> {
  const { data } = await apiClient.get<{ data: SuperAdminDashboardData }>('/superadmin/dashboard')
  return data.data
}
