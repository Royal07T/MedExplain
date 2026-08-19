export type DocumentStatus = 'uploaded' | 'processing' | 'processed' | 'failed'

export type ExtractionMethod = 'text' | 'ocr' | 'none'

export type LabResultStatus = 'normal' | 'high' | 'low' | 'critical_high' | 'critical_low' | 'positive' | 'negative' | 'unknown'

export type AnalysisItemCategory = 'fact' | 'reference_comparison' | 'education' | 'possible_context' | 'question_for_professional'

export interface UserProfile {
  first_name: string | null
  last_name: string | null
  date_of_birth: string | null
  gender: string | null
  avatar_url: string | null
}

export type Plan = 'free' | 'pro'

export interface User {
  id: number
  name: string
  email: string
  role: 'patient' | 'clinician'
  plan: Plan
  email_verified_at: string | null
  created_at: string
  profile: UserProfile | null
}

export interface Document {
  id: number
  original_filename: string
  document_type: string
  file_size: number
  status: DocumentStatus
  extraction_method: ExtractionMethod | null
  error_message: string | null
  created_at: string
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Paginated<T> {
  data: T[]
  links: unknown
  meta: PaginationMeta
}

export interface LabResult {
  name: string
  value: string
  unit: string | null
  reference_range: string | null
  status: LabResultStatus
}

export interface AnalysisItem {
  test_name: string
  category: AnalysisItemCategory
  explanation: string
}

export interface Analysis {
  id: number
  status: 'processing' | 'completed' | 'failed'
  summary: string | null
  disclaimer: string | null
  error_message: string | null
  concerns: string[]
  items: AnalysisItem[]
  lab_results: LabResult[]
  processed_at: string | null
  created_at: string
}

export interface AuthPayload {
  token: string
  user: User
}

export interface LabTestName {
  name: string
  unit: string | null
  last_collected_at: string | null
  count: number
}

export interface TrendPoint {
  date: string | null
  value: string
  status: LabResultStatus
  reference_range: string | null
  document_id: number | null
  document_filename: string | null
}

export interface LabTrend {
  test: string
  unit: string | null
  series: TrendPoint[]
}

export type TimelineEventType =
  | 'document_uploaded'
  | 'document_processed'
  | 'analysis_completed'
  | 'lab_result'

export interface TimelineEvent {
  type: TimelineEventType
  occurred_at: string
  title: string
  description: string | null
  document_id: number | null
}

export interface Medication {
  id: number
  name: string
  strength: string | null
  dosage_form: string | null
  dose: string | null
  frequency: string | null
  route: string | null
  prescriber: string | null
  indications: string | null
  start_date: string | null
  end_date: string | null
  medical_document_id: number | null
  created_at: string
}

export interface AssistantReply {
  reply: string
  disclaimer: string
  sources: string[]
}

export interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
}

export interface HealthRecordLab {
  name: string
  value: string | null
  unit: string | null
  status: LabResultStatus
  reference_range: string | null
  last_collected_at: string | null
}

export interface HealthRecordMedication {
  id: number
  name: string
  strength: string | null
  dosage_form: string | null
  dose: string | null
  frequency: string | null
  route: string | null
  indications: string | null
  medical_document_id: number | null
}

export interface HealthRecord {
  profile: {
    name: string
    email: string
    date_of_birth: string | null
    gender: string | null
  }
  labs: HealthRecordLab[]
  medications: HealthRecordMedication[]
  timeline: TimelineEvent[]
}

export interface PartnerConsent {
  partner_id: number
  partner_name: string
  scopes: string[]
  granted_at: string | null
  revoked_at: string | null
}

export interface AppNotification {
  id: number
  title: string
  body: string | null
  type: string
  data: Record<string, unknown> | null
  read_at: string | null
  created_at: string
}