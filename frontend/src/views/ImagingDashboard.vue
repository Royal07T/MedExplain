<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  getPatientImagingOrders,
  createImagingOrder,
  updateImagingOrderStatus,
  recordImagingResult,
  cancelImagingOrder,
  saveRadiologyReport,
  type ImagingOrder,
  type CreateImagingOrderRequest,
  type ImagingModality,
} from '@/api/imaging'
import { listPatients, type ClinicianPatient } from '@/api/clinician'

const orders = ref<ImagingOrder[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const patientId = ref<number | null>(null)
const patients = ref<ClinicianPatient[]>([])

// Form state
const showForm = ref(false)
const form = ref<CreateImagingOrderRequest>({
  patient_id: patientId.value!,
  modality: 'xray',
  priority: 'routine',
})
const submitting = ref(false)

// Status / report modals
const statusOrder = ref<ImagingOrder | null>(null)
const newStatus = ref<string>('')
const resultOrder = ref<ImagingOrder | null>(null)
const resultForm = ref({ radiation_dose_mgy: null as number | null, image_count: null as number | null, findings: '' })
const reportOrder = ref<ImagingOrder | null>(null)
const reportForm = ref({ findings: '', impression: '', report_text: '', status: 'draft' as 'draft' | 'final' })

const modalityLabels: Record<ImagingModality, string> = {
  xray: 'X-Ray',
  ct: 'CT Scan',
  mri: 'MRI',
  ultrasound: 'Ultrasound',
  nuclear_medicine: 'Nuclear Medicine',
  pet_scan: 'PET Scan',
  fluoroscopy: 'Fluoroscopy',
}

const priorityStyles: Record<string, string> = {
  routine: 'bg-slate-100 text-slate-700',
  urgent: 'bg-amber-100 text-amber-700',
  stat: 'bg-red-100 text-red-700',
}

const statusStyles: Record<string, string> = {
  pending: 'bg-blue-100 text-blue-700',
  scheduled: 'bg-indigo-100 text-indigo-700',
  in_progress: 'bg-amber-100 text-amber-700',
  completed: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-gray-100 text-gray-600',
}

// Load data
async function loadOrders() {
  if (patientId.value == null) return
  loading.value = true
  error.value = null
  try {
    orders.value = await getPatientImagingOrders(patientId.value)
  } catch {
    error.value = 'Failed to load imaging orders'
  } finally {
    loading.value = false
  }
}

async function loadPatients() {
  try {
    patients.value = await listPatients()
    if (patients.value.length && patientId.value == null) {
      patientId.value = patients.value[0].id
      await loadOrders()
    }
  } catch {
    patients.value = []
  }
}

onMounted(loadPatients)

function openForm() {
  if (patientId.value == null) return
  form.value = {
    patient_id: patientId.value,
    modality: 'xray',
    priority: 'routine',
    scheduled_at: undefined,
    body_region: '',
    clinical_indication: '',
    icd_code: '',
    notes: '',
  }
  showForm.value = true
}

async function submitForm() {
  if (submitting.value) return
  submitting.value = true
  error.value = null
  try {
    await createImagingOrder(form.value)
    showForm.value = false
    await loadOrders()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to create imaging order'
  } finally {
    submitting.value = false
  }
}

function openStatus(order: ImagingOrder) {
  statusOrder.value = order
  newStatus.value = order.status
}

async function saveStatus() {
  if (!statusOrder.value) return
  try {
    await updateImagingOrderStatus(statusOrder.value.id, newStatus.value as any)
    statusOrder.value = null
    await loadOrders()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update status'
  }
}

function openResult(order: ImagingOrder) {
  resultOrder.value = order
  resultForm.value = {
    radiation_dose_mgy: order.radiation_dose_mgy,
    image_count: order.image_count,
    findings: order.report?.findings || '',
  }
}

async function saveResult() {
  if (!resultOrder.value) return
  try {
    await recordImagingResult(resultOrder.value.id, {
      radiation_dose_mgy: resultForm.value.radiation_dose_mgy ?? undefined,
      image_count: resultForm.value.image_count ?? undefined,
      findings: resultForm.value.findings || undefined,
    })
    resultOrder.value = null
    await loadOrders()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to record result'
  }
}

function openReport(order: ImagingOrder) {
  reportOrder.value = order
  reportForm.value = {
    findings: order.report?.findings || '',
    impression: order.report?.impression || '',
    report_text: order.report?.report_text || '',
    status: (order.report?.status as 'draft' | 'final') || 'draft',
  }
}

async function saveReport() {
  if (!reportOrder.value) return
  try {
    await saveRadiologyReport(reportOrder.value.id, {
      findings: reportForm.value.findings || undefined,
      impression: reportForm.value.impression || undefined,
      report_text: reportForm.value.report_text || undefined,
      status: reportForm.value.status,
    })
    reportOrder.value = null
    await loadOrders()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save radiology report'
  }
}

async function handleCancel(order: ImagingOrder) {
  if (!confirm('Cancel this imaging order?')) return
  try {
    await cancelImagingOrder(order.id)
    await loadOrders()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to cancel order'
  }
}

function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString()
}

function capitalized(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

onMounted(loadOrders)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Imaging &amp; Radiology</h1>
      <p class="mt-1 text-sm text-slate-500">
        Order imaging studies and manage radiology reports.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <label class="text-sm font-medium text-slate-700">Patient</label>
        <select
          v-model.number="patientId"
          @change="loadOrders"
          class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
        >
          <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <button @click="openForm" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Place Imaging Order</button>
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>

    <div v-else-if="orders.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No imaging orders found</p>
      <p class="mt-1">Place your first imaging order to get started.</p>
    </div>

    <div v-else class="space-y-3">
      <div v-for="order in orders" :key="order.id" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium bg-teal-100 text-teal-700">{{ modalityLabels[order.modality] }}</span>
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="priorityStyles[order.priority]">{{ capitalized(order.priority) }}</span>
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusStyles[order.status]">{{ capitalized(order.status.replace('_', ' ')) }}</span>
              <span v-if="order.icd_code" class="rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600">ICD: {{ order.icd_code }}</span>
            </div>

            <p class="text-sm font-medium text-slate-800">{{ order.body_region || 'Unspecified region' }}</p>
            <p class="mt-1 text-xs text-slate-500">Ordered: {{ formatDate(order.ordered_at) }}</p>
            <p v-if="order.clinical_indication" class="mt-1 text-xs text-slate-500">Indication: {{ order.clinical_indication }}</p>
            <p v-if="order.scheduled_at" class="mt-1 text-xs text-slate-500">Scheduled: {{ formatDate(order.scheduled_at) }}</p>
            <p v-if="order.radiation_dose_mgy !== null" class="mt-1 text-xs text-slate-500">Radiation dose: {{ order.radiation_dose_mgy }} mGy</p>
            <p v-if="order.image_count !== null" class="mt-1 text-xs text-slate-500">Images: {{ order.image_count }}</p>

            <div v-if="order.report" class="mt-3 rounded-lg border border-slate-100 bg-slate-50 p-3">
              <p class="text-xs font-semibold text-slate-700">
                Report ({{ capitalized(order.report.status || 'draft') }})
                <span class="font-normal text-slate-500">— {{ formatDate(order.report.reported_at) }}</span>
              </p>
              <p v-if="order.report.impression" class="mt-1 text-xs text-slate-600">Impression: {{ order.report.impression }}</p>
              <p v-if="order.report.findings" class="mt-1 text-xs text-slate-600">Findings: {{ order.report.findings }}</p>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="openStatus(order)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Status</button>
            <button @click="openResult(order)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Result</button>
            <button @click="openReport(order)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Report</button>
            <button @click="handleCancel(order)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Place Order Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Place Imaging Order</h2>
          <button @click="showForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Modality</label>
              <select v-model="form.modality" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option v-for="(label, key) in modalityLabels" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
              <select v-model="form.priority" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option value="routine">Routine</option>
                <option value="urgent">Urgent</option>
                <option value="stat">STAT</option>
              </select>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Body Region</label>
            <input v-model="form.body_region" type="text" placeholder="e.g., Chest, Head, Lumbar spine" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Clinical Indication</label>
            <textarea v-model="form.clinical_indication" rows="2" placeholder="Reason for study" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">ICD-10 Code</label>
              <input v-model="form.icd_code" type="text" placeholder="e.g., R05" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Scheduled At</label>
              <input v-model="form.scheduled_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Place Order' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Status Modal -->
    <div v-if="statusOrder" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Update Status</h2>
        <select v-model="newStatus" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
          <option value="pending">Pending</option>
          <option value="scheduled">Scheduled</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <div class="mt-4 flex gap-3">
          <button @click="statusOrder = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
          <button @click="saveStatus" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Save</button>
        </div>
      </div>
    </div>

    <!-- Result Modal -->
    <div v-if="resultOrder" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Record Imaging Result</h2>
        <form @submit.prevent="saveResult" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Radiation Dose (mGy)</label>
              <input v-model.number="resultForm.radiation_dose_mgy" type="number" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Image Count</label>
              <input v-model.number="resultForm.image_count" type="number" min="0" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Findings</label>
            <textarea v-model="resultForm.findings" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="resultOrder = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Save Result</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Report Modal -->
    <div v-if="reportOrder" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Radiology Report</h2>
        <form @submit.prevent="saveReport" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Findings</label>
            <textarea v-model="reportForm.findings" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Impression</label>
            <textarea v-model="reportForm.impression" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Report Text</label>
            <textarea v-model="reportForm.report_text" rows="4" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select v-model="reportForm.status" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="draft">Draft</option>
              <option value="final">Final</option>
            </select>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="reportOrder = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Save Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
