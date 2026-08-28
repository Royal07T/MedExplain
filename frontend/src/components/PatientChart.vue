<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  getProblemList,
  getAllergies,
  getVitalSigns,
  getVitalSignTrends,
  getClinicalNotes,
  getTemplates,
  type ProblemList,
  type Allergy,
  type VitalSign,
  type ClinicalNote,
  type ClinicalNoteTemplate
} from '@/api/clinicalDocumentation'
import {
  comprehensiveCDSCheck,
  type CDSAlert
} from '@/api/clinicalDecisionSupport'

const props = defineProps<{
  patientId: number
  patientAge?: number
}>()

const loading = ref(true)
const error = ref<string | null>(null)

// Data
const problems = ref<ProblemList[]>([])
const allergies = ref<Allergy[]>([])
const vitalSigns = ref<VitalSign[]>([])
const vitalSignTrends = ref<any>(null)
const clinicalNotes = ref<ClinicalNote[]>([])
const templates = ref<ClinicalNoteTemplate[]>([])
const cdsAlerts = ref<CDSAlert[]>([])

// UI State
const activeTab = ref('overview')
const showProblemModal = ref(false)
const showAllergyModal = ref(false)
const showVitalSignModal = ref(false)
const showNoteModal = ref(false)
const selectedTemplate = ref<ClinicalNoteTemplate | null>(null)

// Computed
const activeProblems = computed(() => problems.value.filter(p => p.status === 'active'))
const chronicProblems = computed(() => problems.value.filter(p => p.status === 'chronic'))
const severeAllergies = computed(() => allergies.value.filter(a => a.severity === 'severe' || a.severity === 'life_threatening'))
const latestVitalSign = computed(() => vitalSigns.value[0])
const severeAlerts = computed(() => cdsAlerts.value.filter(a => a.severity === 'severe'))
const moderateAlerts = computed(() => cdsAlerts.value.filter(a => a.severity === 'moderate'))

onMounted(async () => {
  await loadPatientData()
})

async function loadPatientData() {
  loading.value = true
  error.value = null
  try {
    const [problemsData, allergiesData, vitalSignsData, notesData, templatesData, cdsData] = await Promise.all([
      getProblemList(props.patientId),
      getAllergies(props.patientId),
      getVitalSigns(props.patientId),
      getClinicalNotes(props.patientId),
      getTemplates(),
      comprehensiveCDSCheck(props.patientId, { age: props.patientAge })
    ])

    problems.value = problemsData
    allergies.value = allergiesData
    vitalSigns.value = vitalSignsData
    clinicalNotes.value = notesData
    templates.value = templatesData
    cdsAlerts.value = cdsData.alerts

    // Load vital sign trends
    if (vitalSigns.value.length > 0) {
      vitalSignTrends.value = await getVitalSignTrends(props.patientId, 30)
    }
  } catch (err) {
    error.value = 'Failed to load patient data'
    console.error(err)
  } finally {
    loading.value = false
  }
}

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}

function getSeverityColor(severity: string): string {
  const colors = {
    mild: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    moderate: 'bg-orange-50 text-orange-700 border-orange-200',
    severe: 'bg-red-50 text-red-700 border-red-200',
    life_threatening: 'bg-red-100 text-red-900 border-red-300'
  }
  return colors[severity as keyof typeof colors] || colors.moderate
}

function getStatusColor(status: string): string {
  const colors = {
    active: 'bg-green-50 text-green-700',
    resolved: 'bg-gray-50 text-gray-700',
    chronic: 'bg-blue-50 text-blue-700',
    recurrent: 'bg-purple-50 text-purple-700'
  }
  return colors[status as keyof typeof colors] || 'bg-gray-50 text-gray-700'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Patient Chart</h1>
        <p class="mt-1 text-sm text-slate-500">Comprehensive clinical documentation</p>
      </div>
      <button
        @click="loadPatientData"
        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
      >
        Refresh
      </button>
    </div>

    <!-- CDS Alerts -->
    <div v-if="severeAlerts.length > 0" class="rounded-lg border border-red-200 bg-red-50 p-4">
      <h3 class="font-semibold text-red-900">⚠️ Critical Alerts</h3>
      <ul class="mt-2 space-y-2">
        <li v-for="(alert, idx) in severeAlerts" :key="idx" class="text-sm text-red-800">
          {{ alert.message }}
        </li>
      </ul>
    </div>

    <div v-if="moderateAlerts.length > 0" class="rounded-lg border border-orange-200 bg-orange-50 p-4">
      <h3 class="font-semibold text-orange-900">⚡ Warnings</h3>
      <ul class="mt-2 space-y-2">
        <li v-for="(alert, idx) in moderateAlerts" :key="idx" class="text-sm text-orange-800">
          {{ alert.message }}
        </li>
      </ul>
    </div>

    <!-- Error State -->
    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
      Loading patient data…
    </div>

    <!-- Tabs -->
    <div v-if="!loading" class="border-b border-slate-200">
      <nav class="-mb-px flex space-x-8">
        <button
          v-for="tab in ['overview', 'problems', 'allergies', 'vitals', 'notes']"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'border-b-2 px-1 py-4 text-sm font-medium capitalize',
            activeTab === tab
              ? 'border-teal-500 text-teal-600'
              : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'
          ]"
        >
          {{ tab }}
        </button>
      </nav>
    </div>

    <!-- Overview Tab -->
    <div v-if="!loading && activeTab === 'overview'" class="space-y-6">
      <!-- Quick Stats -->
      <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-400">Active Problems</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ activeProblems.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-400">Allergies</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ allergies.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-400">Latest Vitals</p>
          <p class="mt-2 text-sm font-medium text-slate-900">
            {{ latestVitalSign ? formatDate(latestVitalSign.recorded_at) : '—' }}
          </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-400">Clinical Notes</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ clinicalNotes.length }}</p>
        </div>
      </div>

      <!-- Severe Allergies Alert -->
      <div v-if="severeAllergies.length > 0" class="rounded-xl border border-red-200 bg-red-50 p-4">
        <h3 class="font-semibold text-red-900">Severe Allergies</h3>
        <div class="mt-2 flex flex-wrap gap-2">
          <span
            v-for="allergy in severeAllergies"
            :key="allergy.id"
            class="rounded-full border border-red-300 bg-red-100 px-3 py-1 text-sm font-medium text-red-900"
          >
            {{ allergy.allergen_name }} ({{ allergy.severity }})
          </span>
        </div>
      </div>

      <!-- Recent Clinical Notes -->
      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Recent Clinical Notes</h2>
        <div v-if="clinicalNotes.length === 0" class="mt-4 text-sm text-slate-500">
          No clinical notes recorded yet.
        </div>
        <div v-else class="mt-3 space-y-3">
          <div
            v-for="note in clinicalNotes.slice(0, 3)"
            :key="note.id"
            class="rounded-lg border border-slate-100 p-4"
          >
            <div class="flex items-center justify-between">
              <span class="rounded-full px-2 py-1 text-xs font-medium capitalize" :class="getStatusColor(note.status)">
                {{ note.status }}
              </span>
              <span class="text-xs text-slate-400">{{ formatDate(note.created_at) }}</span>
            </div>
            <p class="mt-2 text-sm font-medium text-slate-800 capitalize">{{ note.note_type }} Note</p>
            <p v-if="note.assessment" class="mt-1 text-sm text-slate-600 line-clamp-2">{{ note.assessment }}</p>
          </div>
        </div>
      </section>
    </div>

    <!-- Problems Tab -->
    <div v-if="!loading && activeTab === 'problems'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Problem List</h2>
        <button
          @click="showProblemModal = true"
          class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
        >
          Add Problem
        </button>
      </div>

      <div v-if="problems.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
        No problems recorded yet.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="problem in problems"
          :key="problem.id"
          class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="getStatusColor(problem.status)">
                  {{ problem.status }}
                </span>
                <span class="text-sm font-mono text-slate-500">{{ problem.icd10_code }}</span>
              </div>
              <p class="mt-1 font-medium text-slate-900">{{ problem.icd10_description }}</p>
              <p v-if="problem.clinical_notes" class="mt-1 text-sm text-slate-600">{{ problem.clinical_notes }}</p>
            </div>
            <div class="text-right text-xs text-slate-400">
              <p>Onset: {{ formatDate(problem.onset_date) }}</p>
              <p v-if="problem.resolved_date">Resolved: {{ formatDate(problem.resolved_date) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Allergies Tab -->
    <div v-if="!loading && activeTab === 'allergies'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Allergies</h2>
        <button
          @click="showAllergyModal = true"
          class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
        >
          Add Allergy
        </button>
      </div>

      <div v-if="allergies.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
        No allergies recorded.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="allergy in allergies"
          :key="allergy.id"
          class="rounded-xl border p-4 shadow-sm"
          :class="getSeverityColor(allergy.severity)"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="rounded-full px-2 py-1 text-xs font-medium uppercase bg-white/50">
                  {{ allergy.allergen_type }}
                </span>
                <span class="rounded-full px-2 py-1 text-xs font-medium uppercase bg-white/50">
                  {{ allergy.severity }}
                </span>
              </div>
              <p class="mt-1 font-medium">{{ allergy.allergen_name }}</p>
              <p v-if="allergy.reaction_description" class="mt-1 text-sm">{{ allergy.reaction_description }}</p>
            </div>
            <span class="rounded-full px-2 py-1 text-xs font-medium uppercase bg-white/50">
              {{ allergy.status }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Vitals Tab -->
    <div v-if="!loading && activeTab === 'vitals'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Vital Signs</h2>
        <button
          @click="showVitalSignModal = true"
          class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
        >
          Record Vitals
        </button>
      </div>

      <div v-if="vitalSigns.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
        No vital signs recorded yet.
      </div>

      <div v-else>
        <!-- Latest Vitals Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Latest Reading</h3>
          <p class="mt-1 text-xs text-slate-500">{{ formatDate(latestVitalSign?.recorded_at) }}</p>
          
          <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-if="latestVitalSign?.temperature" class="rounded-lg bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Temperature</p>
              <p class="mt-1 text-lg font-semibold text-slate-900">
                {{ latestVitalSign.temperature }}°{{ latestVitalSign.temperature_unit }}
              </p>
            </div>
            <div v-if="latestVitalSign?.heart_rate" class="rounded-lg bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Heart Rate</p>
              <p class="mt-1 text-lg font-semibold text-slate-900">{{ latestVitalSign.heart_rate }} bpm</p>
            </div>
            <div v-if="latestVitalSign?.blood_pressure_systolic" class="rounded-lg bg-slate-50 p-3">
              <p class="text-xs text-slate-500">Blood Pressure</p>
              <p class="mt-1 text-lg font-semibold text-slate-900">
                {{ latestVitalSign.blood_pressure_systolic }}/{{ latestVitalSign.blood_pressure_diastolic }}
              </p>
            </div>
            <div v-if="latestVitalSign?.oxygen_saturation" class="rounded-lg bg-slate-50 p-3">
              <p class="text-xs text-slate-500">O₂ Saturation</p>
              <p class="mt-1 text-lg font-semibold text-slate-900">{{ latestVitalSign.oxygen_saturation }}%</p>
            </div>
          </div>
        </div>

        <!-- History -->
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">History</h3>
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                  <th class="pb-2 pr-4 font-medium">Date</th>
                  <th class="pb-2 pr-4 font-medium">Temp</th>
                  <th class="pb-2 pr-4 font-medium">HR</th>
                  <th class="pb-2 pr-4 font-medium">BP</th>
                  <th class="pb-2 pr-4 font-medium">O₂</th>
                  <th class="pb-2 font-medium">Weight</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="vital in vitalSigns.slice(0, 10)" :key="vital.id" class="border-b border-slate-100">
                  <td class="py-2.5 pr-4 text-slate-600">{{ formatDate(vital.recorded_at) }}</td>
                  <td class="py-2.5 pr-4 text-slate-700">{{ vital.temperature ?? '—' }}</td>
                  <td class="py-2.5 pr-4 text-slate-700">{{ vital.heart_rate ?? '—' }}</td>
                  <td class="py-2.5 pr-4 text-slate-700">
                    {{ vital.blood_pressure_systolic && vital.blood_pressure_diastolic 
                      ? `${vital.blood_pressure_systolic}/${vital.blood_pressure_diastolic}` 
                      : '—' }}
                  </td>
                  <td class="py-2.5 pr-4 text-slate-700">{{ vital.oxygen_saturation ?? '—' }}</td>
                  <td class="py-2.5 text-slate-700">{{ vital.weight ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Clinical Notes Tab -->
    <div v-if="!loading && activeTab === 'notes'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Clinical Notes</h2>
        <button
          @click="showNoteModal = true"
          class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
        >
          New Note
        </button>
      </div>

      <div v-if="clinicalNotes.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
        No clinical notes recorded yet.
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="note in clinicalNotes"
          :key="note.id"
          class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
        >
          <div class="flex items-start justify-between">
            <div>
              <div class="flex items-center gap-2">
                <span class="rounded-full px-2 py-1 text-xs font-medium capitalize" :class="getStatusColor(note.status)">
                  {{ note.status }}
                </span>
                <span class="text-sm font-medium text-slate-600 capitalize">{{ note.note_type }}</span>
              </div>
              <p class="mt-1 text-xs text-slate-400">{{ formatDate(note.created_at) }}</p>
            </div>
            <div v-if="note.cosigner_id" class="text-xs text-slate-500">
              Cosigned by {{ note.cosigner_id }}
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <div v-if="note.subjective">
              <p class="text-xs font-semibold text-slate-400 uppercase">Subjective</p>
              <p class="mt-1 text-sm text-slate-700">{{ note.subjective }}</p>
            </div>
            <div v-if="note.objective">
              <p class="text-xs font-semibold text-slate-400 uppercase">Objective</p>
              <p class="mt-1 text-sm text-slate-700">{{ note.objective }}</p>
            </div>
            <div v-if="note.assessment">
              <p class="text-xs font-semibold text-slate-400 uppercase">Assessment</p>
              <p class="mt-1 text-sm text-slate-700">{{ note.assessment }}</p>
            </div>
            <div v-if="note.plan">
              <p class="text-xs font-semibold text-slate-400 uppercase">Plan</p>
              <p class="mt-1 text-sm text-slate-700">{{ note.plan }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
