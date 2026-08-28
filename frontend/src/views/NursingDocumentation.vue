<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  getCarePlans,
  createCarePlan,
  updateCarePlanStatus,
  getMAR,
  createMAR,
  updateMARStatus,
  getAssessmentTemplates,
  getAssessments,
  createAssessment,
  getFallRiskSummary,
  getHandoffs,
  createHandoff,
  type CarePlan,
  type CarePlanStatus,
  type MedicationAdministration,
  type MedAdminStatus,
  type NursingAssessment,
  type AssessmentType,
  type AssessmentTemplate,
  type FallRiskLevel,
  type ShiftHandoff,
} from '@/api/nursingDocumentation'

const activeTab = ref<'care-plans' | 'mar' | 'assessments' | 'handoffs'>('care-plans')
const loading = ref(false)
const error = ref<string | null>(null)
const patientId = ref<number | null>(null)

// Care plans
const carePlans = ref<CarePlan[]>([])
const showCarePlan = ref(false)
const carePlanForm = ref({ patient_id: null as number | null, title: '', description: '', goals: '', interventions: '' })
const submitting = ref(false)

// MAR
const mar = ref<MedicationAdministration[]>([])
const showMar = ref(false)
const marForm = ref({ patient_id: null as number | null, medication_name: '', dose: '', dose_unit: '', route: '', scheduled_time: '' })

// Assessments
const assessmentTemplates = ref<AssessmentTemplate[]>([])
const assessments = ref<NursingAssessment[]>([])
const fallRisk = ref<NursingAssessment[]>([])
const showAssessment = ref(false)
const assessmentForm = ref({
  patient_id: null as number | null,
  assessment_type: 'shift' as AssessmentType,
  template_name: '',
  findings: '',
  notes: '',
  fall_risk_score: null as number | null,
  fall_risk_level: '' as FallRiskLevel | '',
  pressure_ulcer_stage: '',
})

// Handoffs
const handoffs = ref<ShiftHandoff[]>([])
const showHandoff = ref(false)
const handoffForm = ref({ patient_id: null as number | null, to_nurse_id: null as number | null, unit: '', clinical_summary: '', tasks_to_complete: '', medication_review: '', safety_concerns: '' })

const medAdminStatusOptions: Array<{ value: MedAdminStatus; label: string; classes: string }> = [
  { value: 'given', label: 'Given', classes: 'bg-emerald-100 text-emerald-700' },
  { value: 'refused', label: 'Refused', classes: 'bg-red-100 text-red-700' },
  { value: 'held', label: 'Held', classes: 'bg-amber-100 text-amber-700' },
  { value: 'not_given', label: 'Not Given', classes: 'bg-slate-100 text-slate-600' },
]

const carePlanStatusOptions: Array<{ value: CarePlanStatus; label: string }> = [
  { value: 'active', label: 'Active' },
  { value: 'on_hold', label: 'On Hold' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

const tabs: Array<{ value: typeof activeTab.value; label: string }> = [
  { value: 'care-plans', label: 'Care Plans' },
  { value: 'mar', label: 'MAR' },
  { value: 'assessments', label: 'Assessments' },
  { value: 'handoffs', label: 'Shift Handoffs' },
]

const marStatusClasses = computed(() => Object.fromEntries(medAdminStatusOptions.map((o) => [o.value, o.classes])))

function splitLines(v: string | null | undefined): string[] {
  if (!v) return []
  return v.split('\n').map((s) => s.trim()).filter(Boolean)
}

async function loadAll() {
  loading.value = true
  error.value = null
  try {
    const params = patientId.value ? { patient_id: patientId.value } : undefined
    const [plans, marRows, templates, assessmentsRows, fallRows, handoffRows] = await Promise.all([
      getCarePlans(params),
      getMAR(params),
      getAssessmentTemplates(),
      getAssessments(params),
      getFallRiskSummary(),
      getHandoffs(params),
    ])
    carePlans.value = plans
    mar.value = marRows
    assessmentTemplates.value = templates
    assessments.value = assessmentsRows
    fallRisk.value = fallRows
    handoffs.value = handoffRows
  } catch {
    error.value = 'Failed to load nursing documentation data'
  } finally {
    loading.value = false
  }
}

function openCarePlan() {
  carePlanForm.value = { patient_id: patientId.value, title: '', description: '', goals: '', interventions: '' }
  showCarePlan.value = true
}

async function submitCarePlan() {
  if (submitting.value || !carePlanForm.value.patient_id) return
  submitting.value = true
  error.value = null
  try {
    await createCarePlan({
      patient_id: carePlanForm.value.patient_id,
      title: carePlanForm.value.title,
      description: carePlanForm.value.description || undefined,
      goals: splitLines(carePlanForm.value.goals),
      interventions: splitLines(carePlanForm.value.interventions),
    })
    showCarePlan.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to create care plan'
  } finally {
    submitting.value = false
  }
}

async function handleCarePlanStatus(plan: CarePlan, status: string) {
  try {
    await updateCarePlanStatus(plan.id, status as CarePlanStatus)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update care plan status'
  }
}

function openMar() {
  marForm.value = { patient_id: patientId.value, medication_name: '', dose: '', dose_unit: '', route: '', scheduled_time: '' }
  showMar.value = true
}

async function submitMar() {
  if (submitting.value || !marForm.value.patient_id) return
  submitting.value = true
  error.value = null
  try {
    await createMAR({
      patient_id: marForm.value.patient_id,
      medication_name: marForm.value.medication_name,
      dose: marForm.value.dose || undefined,
      dose_unit: marForm.value.dose_unit || undefined,
      route: marForm.value.route || undefined,
      scheduled_time: marForm.value.scheduled_time || undefined,
    })
    showMar.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to record medication'
  } finally {
    submitting.value = false
  }
}

async function handleMarStatus(ma: MedicationAdministration, status: string) {
  try {
    await updateMARStatus(ma.id, status as MedAdminStatus)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update MAR status'
  }
}

function openAssessment() {
  assessmentForm.value = { patient_id: patientId.value, assessment_type: 'shift', template_name: '', findings: '', notes: '', fall_risk_score: null, fall_risk_level: '', pressure_ulcer_stage: '' }
  showAssessment.value = true
}

async function submitAssessment() {
  if (submitting.value || !assessmentForm.value.patient_id) return
  submitting.value = true
  error.value = null
  try {
    const fallRiskLevel = assessmentForm.value.fall_risk_level || undefined
    await createAssessment({
      patient_id: assessmentForm.value.patient_id,
      assessment_type: assessmentForm.value.assessment_type,
      template_name: assessmentForm.value.template_name || undefined,
      findings: assessmentForm.value.findings || undefined,
      notes: assessmentForm.value.notes || undefined,
      fall_risk_score: assessmentForm.value.fall_risk_score ?? undefined,
      fall_risk_level: (fallRiskLevel as FallRiskLevel) || undefined,
      pressure_ulcer_stage: assessmentForm.value.pressure_ulcer_stage || undefined,
    })
    showAssessment.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save assessment'
  } finally {
    submitting.value = false
  }
}

function openHandoff() {
  handoffForm.value = { patient_id: patientId.value, to_nurse_id: null, unit: '', clinical_summary: '', tasks_to_complete: '', medication_review: '', safety_concerns: '' }
  showHandoff.value = true
}

async function submitHandoff() {
  if (submitting.value || !handoffForm.value.patient_id) return
  submitting.value = true
  error.value = null
  try {
    await createHandoff({
      patient_id: handoffForm.value.patient_id,
      to_nurse_id: handoffForm.value.to_nurse_id || undefined,
      unit: handoffForm.value.unit || undefined,
      clinical_summary: handoffForm.value.clinical_summary || undefined,
      tasks_to_complete: handoffForm.value.tasks_to_complete || undefined,
      medication_review: handoffForm.value.medication_review || undefined,
      safety_concerns: handoffForm.value.safety_concerns || undefined,
    })
    showHandoff.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to record handoff'
  } finally {
    submitting.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Nursing Documentation</h1>
        <p class="mt-1 text-sm text-slate-500">
          Care plans, medication administration record, assessments, and shift handoffs.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-sm text-slate-600">Patient ID:</label>
        <input
          v-model.number="patientId"
          type="number"
          min="1"
          placeholder="All patients"
          @change="loadAll"
          class="w-32 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
        />
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        @click="activeTab = tab.value"
        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium"
        :class="activeTab === tab.value ? 'bg-teal-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>

    <!-- CARE PLANS -->
    <div v-else-if="activeTab === 'care-plans'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Care Plans</h2>
        <button @click="openCarePlan" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">New Care Plan</button>
      </div>
      <div v-if="carePlans.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        No care plans found.
      </div>
      <div v-else class="space-y-3">
        <div v-for="plan in carePlans" :key="plan.id" class="rounded-xl border border-slate-200 bg-white p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-slate-900">{{ plan.title }}</h3>
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="plan.status === 'active' ? 'bg-emerald-100 text-emerald-700' : plan.status === 'completed' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'">{{ plan.status.replace('_', ' ') }}</span>
              </div>
              <p class="mt-1 text-xs text-slate-500">Patient: {{ plan.patient_name || `#${plan.patient_id}` }} · Assignee: {{ plan.assignee_name || '—' }} · Due: {{ plan.due_date || '—' }}</p>
            </div>
            <select
              :value="plan.status"
              @change="handleCarePlanStatus(plan, ($event.target as HTMLSelectElement).value)"
              class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
            >
              <option v-for="opt in carePlanStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <p v-if="plan.description" class="mt-2 text-sm text-slate-600">{{ plan.description }}</p>
          <div v-if="plan.goals && plan.goals.length" class="mt-3 grid grid-cols-2 gap-3">
            <div>
              <p class="text-xs font-semibold text-slate-500">Goals</p>
              <ul class="mt-1 list-inside list-disc text-xs text-slate-700">
                <li v-for="(g, i) in plan.goals" :key="i">{{ g }}</li>
              </ul>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-500">Interventions</p>
              <ul class="mt-1 list-inside list-disc text-xs text-slate-700">
                <li v-for="(inv, i) in plan.interventions" :key="i">{{ inv }}</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MAR -->
    <div v-else-if="activeTab === 'mar'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Medication Administration Record</h2>
        <button @click="openMar" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Record Medication</button>
      </div>
      <div v-if="mar.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        No medication administrations recorded.
      </div>
      <div v-else class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
            <tr>
              <th class="px-4 py-3 font-medium">Patient</th>
              <th class="px-4 py-3 font-medium">Medication</th>
              <th class="px-4 py-3 font-medium">Dose</th>
              <th class="px-4 py-3 font-medium">Scheduled</th>
              <th class="px-4 py-3 font-medium">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="ma in mar" :key="ma.id" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-800">{{ ma.patient_name || `#${ma.patient_id}` }}</td>
              <td class="px-4 py-3 text-slate-800">{{ ma.medication_name }}</td>
              <td class="px-4 py-3 text-slate-700">{{ ma.dose }}{{ ma.dose_unit }} {{ ma.route }}</td>
              <td class="px-4 py-3 text-slate-600">{{ ma.scheduled_time ? new Date(ma.scheduled_time).toLocaleString() : '—' }}</td>
              <td class="px-4 py-3">
                <select
                  :value="ma.status"
                  @change="handleMarStatus(ma, ($event.target as HTMLSelectElement).value)"
                  class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
                >
                  <option v-for="opt in medAdminStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ASSESSMENTS -->
    <div v-else-if="activeTab === 'assessments'" class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Assessments</h2>
        <button @click="openAssessment" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">New Assessment</button>
      </div>

      <!-- Fall risk board -->
      <div v-if="fallRisk.length > 0" class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="mb-3 text-sm font-semibold text-slate-900">Fall Risk Board</h3>
        <div class="flex flex-wrap gap-3">
          <div v-for="f in fallRisk" :key="f.id" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
            <span class="text-sm font-medium text-slate-800">{{ f.patient_name || `#${f.patient_id}` }}</span>
            <span
              class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
              :class="f.fall_risk_level === 'high' ? 'bg-red-100 text-red-700' : f.fall_risk_level === 'moderate' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
            >{{ f.fall_risk_level }} ({{ f.fall_risk_score }})</span>
          </div>
        </div>
      </div>

      <div v-if="assessments.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        No assessments recorded.
      </div>
      <div v-else class="space-y-3">
        <div v-for="a in assessments" :key="a.id" class="rounded-xl border border-slate-200 bg-white p-5">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-semibold capitalize text-slate-900">{{ a.assessment_type.replace('_', ' ') }}</h3>
              <p class="text-xs text-slate-500">Patient: {{ a.patient_name || `#${a.patient_id}` }} · {{ a.template_name || '—' }} · {{ a.assessment_time ? new Date(a.assessment_time).toLocaleString() : '—' }}</p>
            </div>
            <div class="flex gap-2">
              <span v-if="a.fall_risk_level" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium capitalize text-red-700">Fall: {{ a.fall_risk_level }} ({{ a.fall_risk_score }})</span>
              <span v-if="a.pressure_ulcer_stage" class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-700">Ulcer: {{ a.pressure_ulcer_stage }}</span>
            </div>
          </div>
          <p v-if="a.findings" class="mt-2 text-sm text-slate-600">{{ a.findings }}</p>
        </div>
      </div>
    </div>

    <!-- HANDOFFS -->
    <div v-else class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Shift Handoffs</h2>
        <button @click="openHandoff" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">New Handoff</button>
      </div>
      <div v-if="handoffs.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        No shift handoffs recorded.
      </div>
      <div v-else class="space-y-3">
        <div v-for="h in handoffs" :key="h.id" class="rounded-xl border border-slate-200 bg-white p-5">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-semibold text-slate-900">{{ h.patient_name || `Patient #${h.patient_id}` }}</h3>
              <p class="text-xs text-slate-500">From {{ h.from_nurse_name || '—' }} → {{ h.to_nurse_name || 'Unassigned' }} · Unit {{ h.unit || '—' }} · {{ h.handoff_time ? new Date(h.handoff_time).toLocaleString() : '—' }}</p>
            </div>
            <span v-if="h.is_complete" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Complete</span>
          </div>
          <p v-if="h.clinical_summary" class="mt-2 text-sm text-slate-600">{{ h.clinical_summary }}</p>
          <div v-if="h.tasks_to_complete" class="mt-2 rounded-lg bg-slate-50 p-3">
            <p class="text-xs font-semibold text-slate-500">Tasks</p>
            <p class="mt-1 text-sm text-slate-700">{{ h.tasks_to_complete }}</p>
          </div>
          <div v-if="h.safety_concerns" class="mt-2 rounded-lg bg-red-50 p-3">
            <p class="text-xs font-semibold text-red-600">Safety Concerns</p>
            <p class="mt-1 text-sm text-red-700">{{ h.safety_concerns }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- New Care Plan Modal -->
    <div v-if="showCarePlan" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">New Care Plan</h2>
        <form @submit.prevent="submitCarePlan" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
              <input v-model.number="carePlanForm.patient_id" type="number" min="1" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
              <input v-model="carePlanForm.title" type="text" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
            <textarea v-model="carePlanForm.description" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Goals (one per line)</label>
            <textarea v-model="carePlanForm.goals" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Interventions (one per line)</label>
            <textarea v-model="carePlanForm.interventions" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showCarePlan = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Record Medication Modal -->
    <div v-if="showMar" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Record Medication</h2>
        <form @submit.prevent="submitMar" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
              <input v-model.number="marForm.patient_id" type="number" min="1" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Medication</label>
              <input v-model="marForm.medication_name" type="text" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Dose</label>
              <input v-model="marForm.dose" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
              <input v-model="marForm.dose_unit" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Route</label>
              <input v-model="marForm.route" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Scheduled Time</label>
            <input v-model="marForm.scheduled_time" type="datetime-local" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showMar = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Record' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- New Assessment Modal -->
    <div v-if="showAssessment" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">New Assessment</h2>
        <form @submit.prevent="submitAssessment" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
              <input v-model.number="assessmentForm.patient_id" type="number" min="1" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
              <select v-model="assessmentForm.assessment_type" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option v-for="t in assessmentTemplates" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Template</label>
            <input v-model="assessmentForm.template_name" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div v-if="assessmentForm.assessment_type === 'falls'" class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fall Risk Score</label>
              <input v-model.number="assessmentForm.fall_risk_score" type="number" min="0" max="100" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fall Risk Level</label>
              <select v-model="assessmentForm.fall_risk_level" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option value="">Auto-derive</option>
                <option value="low">Low</option>
                <option value="moderate">Moderate</option>
                <option value="high">High</option>
              </select>
            </div>
          </div>
          <div v-if="assessmentForm.assessment_type === 'pressure_ulcer'">
            <label class="mb-1 block text-sm font-medium text-slate-700">Pressure Ulcer Stage</label>
            <input v-model="assessmentForm.pressure_ulcer_stage" type="text" placeholder="e.g., stage_2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Findings</label>
            <textarea v-model="assessmentForm.findings" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showAssessment = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- New Handoff Modal -->
    <div v-if="showHandoff" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">New Shift Handoff</h2>
        <form @submit.prevent="submitHandoff" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
              <input v-model.number="handoffForm.patient_id" type="number" min="1" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">To Nurse ID</label>
              <input v-model.number="handoffForm.to_nurse_id" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
            <input v-model="handoffForm.unit" type="text" placeholder="e.g., 3N" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Clinical Summary</label>
            <textarea v-model="handoffForm.clinical_summary" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Tasks to Complete</label>
            <textarea v-model="handoffForm.tasks_to_complete" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Medication Review</label>
            <textarea v-model="handoffForm.medication_review" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Safety Concerns</label>
            <textarea v-model="handoffForm.safety_concerns" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"></textarea>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showHandoff = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
