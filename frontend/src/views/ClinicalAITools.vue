<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  summarizeNote,
  extractConcepts,
  analyzeSentiment,
  predictReadmission,
  predictLengthOfStay,
  predictDeterioration,
  analyzeImagingOrder,
  type NoteSummary,
  type Concept,
  type ConceptExtraction,
  type SentimentAnalysis,
  type ReadmissionPrediction,
  type LengthOfStayPrediction,
  type DeteriorationPrediction,
  type PredictionVitals,
  type ImagingAnalysis,
} from '@/api/clinicalAI'
import { getPatientImagingOrders, type ImagingOrder } from '@/api/imaging'
import { listPatients, type ClinicianPatient } from '@/api/clinician'

type TabKey = 'nlp' | 'predictive' | 'imaging'
const tabs: Array<{ value: TabKey; label: string }> = [
  { value: 'nlp', label: 'NLP Tools' },
  { value: 'predictive', label: 'Predictive Analytics' },
  { value: 'imaging', label: 'Imaging AI' },
]
const activeTab = ref<TabKey>('nlp')

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

function run<T>(fn: () => Promise<T>): Promise<T | undefined> {
  loading.value = true
  error.value = null
  success.value = null
  return fn()
    .then((result) => {
      success.value = 'Done'
      return result
    })
    .catch((e) => {
      error.value = e?.response?.data?.message || e?.message || 'Request failed'
      return undefined
    })
    .finally(() => {
      loading.value = false
    })
}

// ---------------------------------------------------------------------------
// Summarization
// ---------------------------------------------------------------------------
const summarizeText = ref('')
const summarizeMax = ref(4)
const summary = ref<NoteSummary | null>(null)

async function doSummarize() {
  if (!summarizeText.value.trim()) return
  const result = await run(() => summarizeNote(summarizeText.value, summarizeMax.value))
  if (result) summary.value = result
}

// ---------------------------------------------------------------------------
// Concept extraction
// ---------------------------------------------------------------------------
const conceptsText = ref('')
const extraction = ref<ConceptExtraction | null>(null)

async function doExtractConcepts() {
  if (!conceptsText.value.trim()) return
  const result = await run(() => extractConcepts(conceptsText.value))
  if (result) extraction.value = result
}

function conceptBadge(type: Concept['type']): string {
  return type === 'medication' ? 'bg-violet-100 text-violet-700' : 'bg-cyan-100 text-cyan-700'
}

// ---------------------------------------------------------------------------
// Sentiment
// ---------------------------------------------------------------------------
const sentimentText = ref('')
const sentiment = ref<SentimentAnalysis | null>(null)

async function doSentiment() {
  if (!sentimentText.value.trim()) return
  const result = await run(() => analyzeSentiment(sentimentText.value))
  if (result) sentiment.value = result
}

const sentimentClasses: Record<string, string> = {
  positive: 'bg-emerald-100 text-emerald-700',
  neutral: 'bg-slate-100 text-slate-600',
  negative: 'bg-red-100 text-red-700',
}

// ---------------------------------------------------------------------------
// Readmission
// ---------------------------------------------------------------------------
const readForm = ref({
  age: null as number | null,
  prior_admissions_90d: 0,
  prior_admissions_12m: 0,
  comorbidities: '',
  length_of_stay_days: 0,
  polypharmacy: false,
  hba1c_uncontrolled: false,
  hemoglobin_low: false,
  discharge_to_home: true,
})
const readmission = ref<ReadmissionPrediction | null>(null)

async function doReadmission() {
  const result = await run(() =>
    predictReadmission({
      age: readForm.value.age ?? undefined,
      prior_admissions_90d: readForm.value.prior_admissions_90d,
      prior_admissions_12m: readForm.value.prior_admissions_12m,
      comorbidities: readForm.value.comorbidities
        ? readForm.value.comorbidities.split(',').map((c) => c.trim()).filter(Boolean)
        : [],
      length_of_stay_days: readForm.value.length_of_stay_days,
      polypharmacy: readForm.value.polypharmacy,
      hba1c_uncontrolled: readForm.value.hba1c_uncontrolled,
      hemoglobin_low: readForm.value.hemoglobin_low,
      discharge_to_home: readForm.value.discharge_to_home,
    })
  )
  if (result) readmission.value = result
}

// ---------------------------------------------------------------------------
// Length of stay
// ---------------------------------------------------------------------------
const losForm = ref({
  age: null as number | null,
  admission_type: 'elective',
  acuity: 'non-urgent' as 'non-urgent' | 'urgent' | 'emergent' | 'resuscitation',
  comorbidities: '',
  icu_required: false,
  surgery_required: false,
})
const los = ref<LengthOfStayPrediction | null>(null)

async function doLengthOfStay() {
  const result = await run(() =>
    predictLengthOfStay({
      age: losForm.value.age ?? undefined,
      admission_type: losForm.value.admission_type,
      acuity: losForm.value.acuity,
      comorbidities: losForm.value.comorbidities
        ? losForm.value.comorbidities.split(',').map((c) => c.trim()).filter(Boolean)
        : [],
      icu_required: losForm.value.icu_required,
      surgery_required: losForm.value.surgery_required,
    })
  )
  if (result) los.value = result
}

// ---------------------------------------------------------------------------
// Deterioration
// ---------------------------------------------------------------------------
const vitalsForm = ref<PredictionVitals>({
  heart_rate: undefined,
  respiratory_rate: undefined,
  temperature_c: undefined,
  systolic_bp: undefined,
  spo2: undefined,
  conscious: true,
  on_oxygen: false,
})
const deterioration = ref<DeteriorationPrediction | null>(null)

async function doDeterioration() {
  const result = await run(() =>
    predictDeterioration({
      heart_rate: vitalsForm.value.heart_rate ?? undefined,
      respiratory_rate: vitalsForm.value.respiratory_rate ?? undefined,
      temperature_c: vitalsForm.value.temperature_c ?? undefined,
      systolic_bp: vitalsForm.value.systolic_bp ?? undefined,
      spo2: vitalsForm.value.spo2 ?? undefined,
      conscious: vitalsForm.value.conscious,
      on_oxygen: vitalsForm.value.on_oxygen,
    })
  )
  if (result) deterioration.value = result
}

const levelBadge = (level: string): string =>
  ({
    low: 'bg-emerald-100 text-emerald-700',
    moderate: 'bg-amber-100 text-amber-700',
    high: 'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
  })[level] || 'bg-slate-100 text-slate-600'

// ---------------------------------------------------------------------------
// Imaging AI
// ---------------------------------------------------------------------------
const patients = ref<ClinicianPatient[]>([])
const orders = ref<ImagingOrder[]>([])
const selectedPatientId = ref<number | null>(null)
const selectedOrderId = ref<number | null>(null)
const imagingAnalysis = ref<ImagingAnalysis | null>(null)

onMounted(async () => {
  try {
    patients.value = await listPatients()
  } catch {
    patients.value = []
  }
})

async function loadOrders() {
  orders.value = []
  selectedOrderId.value = null
  imagingAnalysis.value = null
  if (selectedPatientId.value == null) return
  try {
    orders.value = await getPatientImagingOrders(selectedPatientId.value)
  } catch {
    orders.value = []
  }
}

async function doAnalyzeImaging() {
  if (selectedOrderId.value == null) return
  const result = await run(() => analyzeImagingOrder(selectedOrderId.value as number))
  if (result) imagingAnalysis.value = result
}

const priorityBadge = (level: string): string =>
  ({
    routine: 'bg-emerald-100 text-emerald-700',
    urgent: 'bg-amber-100 text-amber-700',
    stat: 'bg-red-100 text-red-700',
  })[level] || 'bg-slate-100 text-slate-600'
</script>

<template>
  <div class="max-w-6xl mx-auto p-6">
    <header class="mb-6">
      <h1 class="text-2xl font-bold text-slate-800">AI Clinical Tools</h1>
      <p class="text-slate-500 mt-1">
        Deterministic decision-support tools. Outputs are estimates to aid
        prioritisation — never a diagnosis or a substitute for clinical judgment.
      </p>
    </header>

    <div class="flex gap-2 mb-6 border-b border-slate-200">
      <button
        v-for="t in tabs"
        :key="t.value"
        class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
        :class="activeTab === t.value ? 'bg-white text-blue-700 border-b-2 border-blue-600' : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = t.value"
      >
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="text-sm text-slate-500 mb-3">Working&hellip;</div>
    <div v-if="error" class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm">{{ error }}</div>
    <div v-if="success && !error" class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ success }}</div>

    <!-- NLP TAB -->
    <div v-if="activeTab === 'nlp'" class="grid grid-cols-1 gap-6">
      <!-- Summarize -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Clinical Note Summarization</h2>
        <textarea v-model="summarizeText" rows="4" placeholder="Paste a clinical note to summarize&hellip;"
          class="w-full rounded-lg border border-slate-300 p-3 text-sm" />
        <div class="flex items-center gap-3 mt-3">
          <label class="text-sm text-slate-600">Max sentences
            <input v-model.number="summarizeMax" type="number" min="1" max="10"
              class="ml-2 w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm" />
          </label>
          <button @click="doSummarize" :disabled="loading || !summarizeText.trim()"
            class="ml-auto rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Summarize</button>
        </div>
        <div v-if="summary" class="mt-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
          <p class="text-sm text-slate-700">{{ summary.summary }}</p>
          <p class="text-xs text-slate-400 mt-2">
            {{ summary.retained_sentence_count }} of {{ summary.original_sentence_count }} sentences retained
          </p>
        </div>
      </section>

      <!-- Concepts -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Clinical Concept Extraction</h2>
        <textarea v-model="conceptsText" rows="3" placeholder="Paste text to extract medications & diagnoses&hellip;"
          class="w-full rounded-lg border border-slate-300 p-3 text-sm" />
        <button @click="doExtractConcepts" :disabled="loading || !conceptsText.trim()"
          class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Extract</button>
        <div v-if="extraction && extraction.concepts.length" class="mt-4 flex flex-wrap gap-2">
          <span v-for="(c, i) in extraction.concepts" :key="i"
            class="rounded-full px-3 py-1 text-xs font-medium" :class="conceptBadge(c.type)">
            {{ c.type }}: {{ c.value }}
          </span>
        </div>
      </section>

      <!-- Sentiment -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Patient Feedback Sentiment</h2>
        <textarea v-model="sentimentText" rows="3" placeholder="Paste patient feedback&hellip;"
          class="w-full rounded-lg border border-slate-300 p-3 text-sm" />
        <button @click="doSentiment" :disabled="loading || !sentimentText.trim()"
          class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Analyze</button>
        <div v-if="sentiment" class="mt-4 flex items-center gap-3">
          <span class="rounded-full px-3 py-1 text-xs font-medium" :class="sentimentClasses[sentiment.label]">
            {{ sentiment.label }}
          </span>
          <span class="text-sm text-slate-600">Score: {{ sentiment.score }}</span>
          <span class="text-xs text-slate-400">(+{{ sentiment.positive_hits }} / -{{ sentiment.negative_hits }})</span>
        </div>
      </section>
    </div>

    <!-- PREDICTIVE TAB -->
    <div v-if="activeTab === 'predictive'" class="grid grid-cols-1 gap-6">
      <!-- Readmission -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Readmission Risk (30-day)</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
          <label>Age
            <input v-model.number="readForm.age" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Admissions (90d)
            <input v-model.number="readForm.prior_admissions_90d" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Admissions (12m)
            <input v-model.number="readForm.prior_admissions_12m" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label class="col-span-2">Comorbidities (comma separated)
            <input v-model="readForm.comorbidities"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>LOS (days)
            <input v-model.number="readForm.length_of_stay_days" type="number" min="0" step="0.1"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700">
          <label class="flex items-center gap-2"><input v-model="readForm.polypharmacy" type="checkbox" /> Polypharmacy</label>
          <label class="flex items-center gap-2"><input v-model="readForm.hba1c_uncontrolled" type="checkbox" /> Uncontrolled HbA1c</label>
          <label class="flex items-center gap-2"><input v-model="readForm.hemoglobin_low" type="checkbox" /> Low Haemoglobin</label>
          <label class="flex items-center gap-2"><input v-model="readForm.discharge_to_home" type="checkbox" /> Discharge to home</label>
        </div>
        <button @click="doReadmission" :disabled="loading"
          class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Predict</button>
        <div v-if="readmission" class="mt-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
          <div class="flex items-center gap-3">
            <span class="text-3xl font-bold text-slate-800">{{ readmission.score }}</span>
            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="levelBadge(readmission.level)">
              {{ readmission.level }}
            </span>
          </div>
          <ul v-if="readmission.contributors.length" class="mt-3 list-disc list-inside text-xs text-slate-500">
            <li v-for="(c, i) in readmission.contributors" :key="i">{{ c }}</li>
          </ul>
        </div>
      </section>

      <!-- Length of stay -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Length of Stay Prediction</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
          <label>Age
            <input v-model.number="losForm.age" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Admission type
            <select v-model="losForm.admission_type" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1">
              <option value="elective">Elective</option>
              <option value="emergency">Emergency</option>
            </select>
          </label>
          <label>Acuity
            <select v-model="losForm.acuity" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1">
              <option value="non-urgent">Non-urgent</option>
              <option value="urgent">Urgent</option>
              <option value="emergent">Emergent</option>
              <option value="resuscitation">Resuscitation</option>
            </select>
          </label>
          <label class="col-span-2">Comorbidities (comma separated)
            <input v-model="losForm.comorbidities" class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700">
          <label class="flex items-center gap-2"><input v-model="losForm.icu_required" type="checkbox" /> ICU required</label>
          <label class="flex items-center gap-2"><input v-model="losForm.surgery_required" type="checkbox" /> Surgery required</label>
        </div>
        <button @click="doLengthOfStay" :disabled="loading"
          class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Predict</button>
        <div v-if="los" class="mt-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
          <p class="text-sm text-slate-700">
            Estimated <span class="font-semibold">{{ los.predicted_days }} days</span>
            (range {{ los.range_min }}&ndash;{{ los.range_max }} days), confidence
            {{ Math.round(los.confidence * 100) }}%
          </p>
          <p v-if="los.drivers.length" class="mt-2 text-xs text-slate-500">
            Drivers: {{ los.drivers.join(', ') }}
          </p>
        </div>
      </section>

      <!-- Deterioration -->
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Deterioration Early-Warning</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
          <label>Heart rate (bpm)
            <input v-model.number="vitalsForm.heart_rate" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Resp rate (min)
            <input v-model.number="vitalsForm.respiratory_rate" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Temp (°C)
            <input v-model.number="vitalsForm.temperature_c" type="number" step="0.1"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>Systolic BP
            <input v-model.number="vitalsForm.systolic_bp" type="number" min="0"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
          <label>SpO2 (%)
            <input v-model.number="vitalsForm.spo2" type="number" min="0" max="100"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1" />
          </label>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-700">
          <label class="flex items-center gap-2"><input v-model="vitalsForm.conscious" type="checkbox" /> Conscious</label>
          <label class="flex items-center gap-2"><input v-model="vitalsForm.on_oxygen" type="checkbox" /> On oxygen</label>
        </div>
        <button @click="doDeterioration" :disabled="loading"
          class="mt-3 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">Score</button>
        <div v-if="deterioration" class="mt-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
          <div class="flex items-center gap-3">
            <span class="text-3xl font-bold text-slate-800">{{ deterioration.score }}</span>
            <span class="rounded-full px-3 py-1 text-xs font-medium" :class="levelBadge(deterioration.level)">
              {{ deterioration.level }}
            </span>
          </div>
          <ul v-if="deterioration.red_flags.length" class="mt-3 list-disc list-inside text-xs text-slate-500">
            <li v-for="(f, i) in deterioration.red_flags" :key="i">{{ f }}</li>
          </ul>
        </div>
      </section>
    </div>

    <!-- IMAGING AI TAB -->
    <div v-if="activeTab === 'imaging'" class="grid grid-cols-1 gap-6">
      <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-semibold text-slate-800 mb-3">Reading-Priority Analysis</h2>
        <p class="text-sm text-slate-500 mb-4">
          Select a patient and an imaging order to get a deterministic reading
          priority suggestion, actionable recommendations, and quality hints.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <label>Patient
            <select v-model="selectedPatientId" @change="loadOrders"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1">
              <option :value="null" disabled>Select a patient&hellip;</option>
              <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label>Imaging order
            <select v-model="selectedOrderId"
              class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1">
              <option :value="null" disabled>Select an order&hellip;</option>
              <option v-for="o in orders" :key="o.id" :value="o.id">
                #{{ o.id }} — {{ o.modality }} / {{ o.body_region || 'n/a' }} ({{ o.priority }})
              </option>
            </select>
          </label>
        </div>
        <button @click="doAnalyzeImaging" :disabled="loading || selectedOrderId == null"
          class="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm text-white disabled:opacity-50">
          Analyze
        </button>

        <div v-if="imagingAnalysis" class="mt-5 grid grid-cols-1 gap-4">
          <div class="p-4 rounded-lg bg-slate-50 border border-slate-200">
            <div class="flex items-center gap-3">
              <span class="text-sm font-semibold text-slate-700">Recommended priority</span>
              <span class="rounded-full px-3 py-1 text-xs font-medium" :class="priorityBadge(imagingAnalysis.priority_level)">
                {{ imagingAnalysis.priority_level }}
              </span>
            </div>
            <p class="mt-2 text-sm text-slate-600">{{ imagingAnalysis.rationale }}</p>
          </div>

          <div v-if="imagingAnalysis.recommendations.length" class="p-4 rounded-lg bg-white border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-800 mb-2">Recommendations</h3>
            <ul class="space-y-2">
              <li v-for="(r, i) in imagingAnalysis.recommendations" :key="i">
                <span class="text-sm font-medium text-slate-700">{{ r.title }}</span>
                <span class="text-xs text-slate-400"> ({{ r.priority_impact }})</span>
                <p class="text-xs text-slate-500">{{ r.detail }}</p>
              </li>
            </ul>
          </div>

          <div v-if="imagingAnalysis.quality_hints.length" class="p-4 rounded-lg bg-white border border-slate-200">
            <h3 class="text-sm font-semibold text-slate-800 mb-2">Quality hints</h3>
            <ul class="list-disc list-inside text-xs text-slate-500 space-y-1">
              <li v-for="(h, i) in imagingAnalysis.quality_hints" :key="i">{{ h }}</li>
            </ul>
          </div>

          <p class="text-xs text-slate-400">{{ imagingAnalysis.disclaimer }}</p>
        </div>
      </section>
    </div>
  </div>
</template>
