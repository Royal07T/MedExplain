export type DocumentStatus = 'uploaded' | 'processing' | 'processed' | 'failed'

export type ExtractionMethod = 'text' | 'ocr' | 'none'

export type LabResultStatus = 'normal' | 'high' | 'low' | 'critical_high' | 'critical_low' | 'positive' | 'negative' | 'unknown'

export type AnalysisItemCategory = 'fact' | 'reference_comparison' | 'education' | 'possible_context' | 'question_for_professional'

export interface UserProfile {
  first_name: string | null
  last_name: string | null
  date_of_birth: string | null
  gender: string | null
}

export interface User {
  id: number
  name: string
  email: string
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