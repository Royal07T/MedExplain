import { apiClient } from './client'

// ---------------------------------------------------------------------------
// 5.1 NLP
// ---------------------------------------------------------------------------

export interface NoteSummary {
  summary: string
  original_sentence_count: number
  retained_sentence_count: number
}

export interface Concept {
  type: 'medication' | 'diagnosis'
  value: string
  confidence: number
}

export interface ConceptExtraction {
  concepts: Concept[]
}

export interface SentimentAnalysis {
  label: 'positive' | 'neutral' | 'negative'
  score: number
  positive_hits: number
  negative_hits: number
}

export async function summarizeNote(text: string, maxSentences = 4): Promise<NoteSummary> {
  const { data } = await apiClient.post<{ success: boolean; data: NoteSummary }>(
    '/clinician/ai/nlp/summarize',
    { text, max_sentences: maxSentences }
  )
  return data.data
}

export async function extractConcepts(text: string): Promise<ConceptExtraction> {
  const { data } = await apiClient.post<{ success: boolean; data: ConceptExtraction }>(
    '/clinician/ai/nlp/concepts',
    { text }
  )
  return data.data
}

export async function analyzeSentiment(text: string): Promise<SentimentAnalysis> {
  const { data } = await apiClient.post<{ success: boolean; data: SentimentAnalysis }>(
    '/clinician/ai/nlp/sentiment',
    { text }
  )
  return data.data
}

// ---------------------------------------------------------------------------
// 5.2 Predictive Analytics
// ---------------------------------------------------------------------------

export interface ReadmissionPrediction {
  score: number
  level: 'low' | 'moderate' | 'high'
  contributors: string[]
}

export interface LengthOfStayPrediction {
  predicted_days: number
  range_min: number
  range_max: number
  model: string
  confidence: number
  drivers: string[]
}

export interface DeteriorationPrediction {
  score: number
  level: 'low' | 'moderate' | 'high' | 'critical'
  components: Record<string, number>
  red_flags: string[]
}

export interface PredictionVitals {
  heart_rate?: number
  respiratory_rate?: number
  temperature_c?: number
  systolic_bp?: number
  diastolic_bp?: number
  spo2?: number
  conscious?: boolean
  on_oxygen?: boolean
}

export async function predictReadmission(payload: {
  age?: number
  prior_admissions_90d?: number
  prior_admissions_12m?: number
  comorbidities?: string[]
  length_of_stay_days?: number
  polypharmacy?: boolean
  hba1c_uncontrolled?: boolean
  hemoglobin_low?: boolean
  discharge_to_home?: boolean
}): Promise<ReadmissionPrediction> {
  const { data } = await apiClient.post<{ success: boolean; data: ReadmissionPrediction }>(
    '/clinician/ai/predictive/readmission',
    payload
  )
  return data.data
}

export async function predictLengthOfStay(payload: {
  age?: number
  admission_type?: string
  acuity?: 'non-urgent' | 'urgent' | 'emergent' | 'resuscitation'
  comorbidities?: string[]
  icu_required?: boolean
  surgery_required?: boolean
}): Promise<LengthOfStayPrediction> {
  const { data } = await apiClient.post<{ success: boolean; data: LengthOfStayPrediction }>(
    '/clinician/ai/predictive/length-of-stay',
    payload
  )
  return data.data
}

export async function predictDeterioration(vitals: PredictionVitals): Promise<DeteriorationPrediction> {
  const { data } = await apiClient.post<{ success: boolean; data: DeteriorationPrediction }>(
    '/clinician/ai/predictive/deterioration',
    { vitals }
  )
  return data.data
}
