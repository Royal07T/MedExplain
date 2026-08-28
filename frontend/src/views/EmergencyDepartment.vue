<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  checkInPatient,
  assignTriage,
  assignClinician,
  updateQueueStatus,
  setDisposition,
  getTrackBoard,
  dispatchAmbulance,
  updateAmbulanceStatus,
  getEDDashboard,
  type EmergencyVisit,
  type AmbulanceDispatch,
  type EDDashboard,
  type AcuityLevel,
  type QueueStatus,
  type Disposition,
  type AmbulanceStatus,
} from '@/api/emergency'

const visits = ref<EmergencyVisit[]>([])
const dashboard = ref<EDDashboard | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Check-in
const showCheckIn = ref(false)
const checkInForm = ref({ patient_id: null as number | null, chief_complaint: '', vitals_summary: '' })
const submitting = ref(false)

// Triage
const triageTarget = ref<EmergencyVisit | null>(null)
const triageLevel = ref<AcuityLevel>('non-urgent')

// Assign clinician
const assignTarget = ref<EmergencyVisit | null>(null)
const clinicianId = ref(1)

// Queue status (in-place select)
// Disposition (in-place select)

// Ambulance
const showAmbulance = ref(false)
const ambulanceForm = ref({ patient_id: null as number | null, emergency_visit_id: null as number | null, pickup_location: '', destination_hospital: '', vehicle_id: '' })
const activeAmbulances = ref<AmbulanceDispatch[]>([])

const acuityOptions: Array<{ value: AcuityLevel; label: string; classes: string }> = [
  { value: 'resuscitation', label: 'Resuscitation', classes: 'bg-red-100 text-red-700' },
  { value: 'emergent', label: 'Emergent', classes: 'bg-orange-100 text-orange-700' },
  { value: 'urgent', label: 'Urgent', classes: 'bg-amber-100 text-amber-700' },
  { value: 'non-urgent', label: 'Non-urgent', classes: 'bg-emerald-100 text-emerald-700' },
]

const queueOptions: Array<{ value: QueueStatus; label: string }> = [
  { value: 'waiting', label: 'Waiting' },
  { value: 'in_triage', label: 'In Triage' },
  { value: 'being_seen', label: 'Being Seen' },
  { value: 'admitted', label: 'Admitted' },
  { value: 'discharged', label: 'Discharged' },
]

const dispositionOptions: Array<{ value: Disposition; label: string }> = [
  { value: 'admitted', label: 'Admit' },
  { value: 'discharged', label: 'Discharge' },
  { value: 'transferred', label: 'Transfer' },
  { value: 'observation', label: 'Observation' },
]

const ambulanceStatusOptions: Array<{ value: AmbulanceStatus; label: string }> = [
  { value: 'dispatched', label: 'Dispatched' },
  { value: 'en_route', label: 'En Route' },
  { value: 'on_scene', label: 'On Scene' },
  { value: 'transporting', label: 'Transporting' },
  { value: 'delivered', label: 'Delivered' },
]

const acuityClasses = computed(() => Object.fromEntries(acuityOptions.map((o) => [o.value, o.classes])))

function acuityBadge(level: AcuityLevel | undefined | null): string {
  if (!level) return 'bg-slate-100 text-slate-600'
  return acuityClasses.value[level] || 'bg-slate-100 text-slate-600'
}

async function loadAll() {
  loading.value = true
  error.value = null
  try {
    const [v, d] = await Promise.all([getTrackBoard(), getEDDashboard()])
    visits.value = v
    dashboard.value = d
    activeAmbulances.value = d.active_ambulances || []
  } catch {
    error.value = 'Failed to load emergency department data'
  } finally {
    loading.value = false
  }
}

function openCheckIn() {
  checkInForm.value = { patient_id: null, chief_complaint: '', vitals_summary: '' }
  showCheckIn.value = true
}

async function submitCheckIn() {
  if (submitting.value || !checkInForm.value.patient_id) return
  submitting.value = true
  error.value = null
  try {
    await checkInPatient({
      patient_id: checkInForm.value.patient_id,
      chief_complaint: checkInForm.value.chief_complaint || undefined,
      vitals_summary: checkInForm.value.vitals_summary || undefined,
    })
    showCheckIn.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to check in patient'
  } finally {
    submitting.value = false
  }
}

function openTriage(v: EmergencyVisit) {
  triageTarget.value = v
  triageLevel.value = v.acuity_level || 'non-urgent'
}

async function submitTriage() {
  if (!triageTarget.value) return
  try {
    await assignTriage(triageTarget.value.id, triageLevel.value)
    triageTarget.value = null
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to assign triage'
  }
}

function openAssign(v: EmergencyVisit) {
  assignTarget.value = v
  clinicianId.value = 1
}

async function submitAssign() {
  if (!assignTarget.value) return
  try {
    await assignClinician(assignTarget.value.id, clinicianId.value)
    assignTarget.value = null
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to assign clinician'
  }
}

async function handleQueue(v: EmergencyVisit, status: string) {
  try {
    await updateQueueStatus(v.id, status as QueueStatus)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update queue status'
  }
}

async function handleDisposition(v: EmergencyVisit, disposition: string) {
  try {
    await setDisposition(v.id, disposition as Disposition)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to set disposition'
  }
}

function openAmbulance() {
  ambulanceForm.value = { patient_id: null, emergency_visit_id: null, pickup_location: '', destination_hospital: '', vehicle_id: '' }
  showAmbulance.value = true
}

async function submitAmbulance() {
  if (submitting.value) return
  submitting.value = true
  error.value = null
  try {
    await dispatchAmbulance({
      patient_id: ambulanceForm.value.patient_id || undefined,
      emergency_visit_id: ambulanceForm.value.emergency_visit_id || undefined,
      pickup_location: ambulanceForm.value.pickup_location || undefined,
      destination_hospital: ambulanceForm.value.destination_hospital || undefined,
      vehicle_id: ambulanceForm.value.vehicle_id || undefined,
    })
    showAmbulance.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to dispatch ambulance'
  } finally {
    submitting.value = false
  }
}

async function handleAmbulanceStatus(a: AmbulanceDispatch, status: string) {
  try {
    await updateAmbulanceStatus(a.id, status as AmbulanceStatus)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update ambulance status'
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Emergency Department</h1>
        <p class="mt-1 text-sm text-slate-500">
          Triage, track board, ambulance dispatch, and crowding analytics.
        </p>
      </div>
      <div class="flex gap-2">
        <button @click="openCheckIn" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Check In Patient</button>
        <button @click="openAmbulance" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Dispatch Ambulance</button>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Dashboard Analytics -->
    <div class="grid grid-cols-5 gap-4">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Active Visits</p>
        <p class="text-2xl font-bold text-slate-900">{{ dashboard?.active_visits ?? 0 }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Arrivals Today</p>
        <p class="text-2xl font-bold text-blue-700">{{ dashboard?.arrivals_today ?? 0 }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Avg LOS (min)</p>
        <p class="text-2xl font-bold text-teal-700">{{ Math.round(dashboard?.average_los_minutes ?? 0) }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Crowding</p>
        <p class="text-2xl font-bold text-emerald-700">{{ dashboard?.crowding_ratio ?? 0 }}%</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Active Ambulances</p>
        <p class="text-2xl font-bold text-amber-700">{{ activeAmbulances.length }}</p>
      </div>
    </div>

    <!-- Acuity Breakdown -->
    <div v-if="dashboard" class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="mb-3 text-sm font-semibold text-slate-900">Acuity Breakdown (Active)</h2>
      <div class="flex flex-wrap gap-3">
        <div v-for="opt in acuityOptions" :key="opt.value" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2">
          <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="opt.classes">{{ opt.label }}</span>
          <span class="text-lg font-bold text-slate-900">{{ dashboard.acuity_breakdown?.[opt.value] ?? 0 }}</span>
        </div>
      </div>
    </div>

    <!-- Ambulance Roster -->
    <div v-if="activeAmbulances.length > 0" class="rounded-xl border border-slate-200 bg-white p-5">
      <h2 class="mb-3 text-sm font-semibold text-slate-900">Active Ambulances</h2>
      <div class="space-y-2">
        <div v-for="a in activeAmbulances" :key="a.id" class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
          <div>
            <p class="text-sm font-medium text-slate-800">{{ a.patient_name || 'Unknown patient' }}</p>
            <p class="text-xs text-slate-500">To {{ a.destination_hospital || '—' }} · {{ a.vehicle_id || 'No vehicle' }}</p>
          </div>
          <select
            :value="a.status"
            @change="handleAmbulanceStatus(a, ($event.target as HTMLSelectElement).value)"
            class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
          >
            <option v-for="opt in ambulanceStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Track Board -->
    <div class="rounded-xl border border-slate-200 bg-white">
      <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Track Board</h2>
      </div>

      <div v-if="loading" class="p-8 text-center text-sm text-slate-500">Loading…</div>

      <div v-else-if="visits.length === 0" class="p-8 text-center text-sm text-slate-500">
        <p class="text-base font-medium text-slate-700">No patients in the ED</p>
        <p class="mt-1">Check in a patient to begin.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
            <tr>
              <th class="px-5 py-3 font-medium">Patient</th>
              <th class="px-3 py-3 font-medium">Acuity</th>
              <th class="px-3 py-3 font-medium">Queue</th>
              <th class="px-3 py-3 font-medium">Clinician</th>
              <th class="px-3 py-3 font-medium">LOS (min)</th>
              <th class="px-3 py-3 font-medium">Disposition</th>
              <th class="px-3 py-3 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="v in visits" :key="v.id" class="hover:bg-slate-50">
              <td class="px-5 py-3">
                <p class="font-medium text-slate-800">{{ v.patient_name || `Patient #${v.patient_id}` }}</p>
                <p class="text-xs text-slate-500">{{ v.chief_complaint || '—' }}</p>
              </td>
              <td class="px-3 py-3">
                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="acuityBadge(v.acuity_level)">{{ v.acuity_level?.replace('_', ' ') || 'Pending' }}</span>
              </td>
              <td class="px-3 py-3">
                <select
                  :value="v.queue_status"
                  @change="handleQueue(v, ($event.target as HTMLSelectElement).value)"
                  class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
                >
                  <option v-for="opt in queueOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </td>
              <td class="px-3 py-3 text-slate-700">{{ v.clinician_name || 'Unassigned' }}</td>
              <td class="px-3 py-3 text-slate-700">{{ v.length_of_stay_minutes }}</td>
              <td class="px-3 py-3">
                <select
                  v-if="v.disposition"
                  :value="v.disposition"
                  disabled
                  class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs capitalize text-slate-500"
                >
                  <option :value="v.disposition">{{ v.disposition }}</option>
                </select>
                <select
                  v-else
                  class="rounded border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700"
                  @change="handleDisposition(v, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="" disabled selected>Set…</option>
                  <option v-for="opt in dispositionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
              </td>
              <td class="px-3 py-3">
                <div class="flex flex-wrap gap-1.5">
                  <button v-if="!v.disposition" @click="openTriage(v)" class="rounded border border-teal-300 bg-white px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50">Triage</button>
                  <button v-if="!v.disposition" @click="openAssign(v)" class="rounded border border-blue-300 bg-white px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50">Assign</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Check In Modal -->
    <div v-if="showCheckIn" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Check In Patient</h2>
        <form @submit.prevent="submitCheckIn" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
            <input v-model.number="checkInForm.patient_id" type="number" min="1" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Chief Complaint</label>
            <input v-model="checkInForm.chief_complaint" type="text" placeholder="e.g., Chest pain" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Vitals Summary</label>
            <input v-model="checkInForm.vitals_summary" type="text" placeholder="e.g., BP 120/80, HR 88" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showCheckIn = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Check In' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Triage Modal -->
    <div v-if="triageTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Triage: {{ triageTarget.patient_name || `Patient #${triageTarget.patient_id}` }}</h2>
        <form @submit.prevent="submitTriage" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Acuity Level</label>
            <select v-model="triageLevel" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option v-for="opt in acuityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="triageTarget = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Assign Modal -->
    <div v-if="assignTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Assign Clinician</h2>
        <form @submit.prevent="submitAssign" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Clinician ID</label>
            <input v-model.number="clinicianId" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="assignTarget = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Assign</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Ambulance Modal -->
    <div v-if="showAmbulance" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Dispatch Ambulance</h2>
        <form @submit.prevent="submitAmbulance" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
              <input v-model.number="ambulanceForm.patient_id" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Visit ID</label>
              <input v-model.number="ambulanceForm.emergency_visit_id" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Pickup Location</label>
            <input v-model="ambulanceForm.pickup_location" type="text" placeholder="e.g., 123 Main St" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Destination Hospital</label>
              <input v-model="ambulanceForm.destination_hospital" type="text" placeholder="e.g., East General" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Vehicle ID</label>
              <input v-model="ambulanceForm.vehicle_id" type="text" placeholder="e.g., AMB-7" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showAmbulance = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Dispatching…' : 'Dispatch' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
