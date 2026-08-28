<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  getWards,
  createWard,
  getWardBeds,
  addWardBeds,
  assignPatientToBed,
  dischargeBed,
  updateBedCleaning,
  getBedUtilization,
  type Ward,
  type Bed,
  type BedUtilization,
  type CleaningStatus,
} from '@/api/bedManagement'

const wards = ref<Ward[]>([])
const bedsByWard = ref<Record<number, Bed[]>>({})
const utilization = ref<BedUtilization | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Ward form
const showWardForm = ref(false)
const wardForm = ref({ name: '', code: '', floor: '', location: '', capacity: null as number | null })
const submitting = ref(false)

// Add beds
const addBedsWard = ref<Ward | null>(null)
const addBedsCount = ref(1)

// Assign
const assignBedTarget = ref<Bed | null>(null)
const assignPatientId = ref<number>(1)

const cleaningOptions: Array<{ value: CleaningStatus; label: string }> = [
  { value: 'clean', label: 'Clean' },
  { value: 'needs_cleaning', label: 'Needs Cleaning' },
  { value: 'being_cleaned', label: 'Being Cleaned' },
]

const totalBeds = computed(() => wards.value.reduce((sum, w) => sum + w.beds_count, 0))
const totalOccupied = computed(() => wards.value.reduce((sum, w) => sum + w.occupied_beds_count, 0))
const totalAvailable = computed(() => totalBeds.value - totalOccupied.value)

const cleaningStyles: Record<string, string> = {
  clean: 'border-emerald-300 bg-emerald-50',
  needs_cleaning: 'border-red-300 bg-red-50',
  being_cleaned: 'border-amber-300 bg-amber-50',
  occupied: 'border-blue-300 bg-blue-50',
}

async function loadAll() {
  loading.value = true
  error.value = null
  try {
    const [w, u] = await Promise.all([getWards(), getBedUtilization()])
    wards.value = w
    utilization.value = u
    await Promise.all(wards.value.map((ward) => loadWardBeds(ward.id)))
  } catch {
    error.value = 'Failed to load bed management data'
  } finally {
    loading.value = false
  }
}

async function loadWardBeds(wardId: number) {
  bedsByWard.value[wardId] = await getWardBeds(wardId)
}

function openWardForm() {
  wardForm.value = { name: '', code: '', floor: '', location: '', capacity: null }
  showWardForm.value = true
}

async function submitWard() {
  if (submitting.value) return
  submitting.value = true
  error.value = null
  try {
    await createWard(wardForm.value)
    showWardForm.value = false
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to create ward'
  } finally {
    submitting.value = false
  }
}

function openAddBeds(ward: Ward) {
  addBedsWard.value = ward
  addBedsCount.value = 1
}

async function submitAddBeds() {
  if (!addBedsWard.value) return
  try {
    await addWardBeds(addBedsWard.value.id, addBedsCount.value)
    addBedsWard.value = null
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to add beds'
  }
}

function openAssign(bed: Bed) {
  assignBedTarget.value = bed
  assignPatientId.value = 1
}

async function submitAssign() {
  if (!assignBedTarget.value) return
  try {
    await assignPatientToBed(assignBedTarget.value.id, assignPatientId.value)
    assignBedTarget.value = null
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to assign bed'
  }
}

async function handleDischarge(bed: Bed) {
  if (!confirm(`Discharge patient from bed ${bed.bed_number}?`)) return
  try {
    await dischargeBed(bed.id)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to discharge bed'
  }
}

async function handleCleaning(bed: Bed, status: CleaningStatus) {
  try {
    await updateBedCleaning(bed.id, status)
    await loadAll()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to update cleaning status'
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Bed Management</h1>
      <p class="mt-1 text-sm text-slate-500">
        Manage wards, bed availability, assignments, and cleaning.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Utilization Summary -->
    <div class="grid grid-cols-4 gap-4">
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Total Beds</p>
        <p class="text-2xl font-bold text-slate-900">{{ totalBeds }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Occupied</p>
        <p class="text-2xl font-bold text-blue-700">{{ totalOccupied }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Available</p>
        <p class="text-2xl font-bold text-emerald-700">{{ totalAvailable }}</p>
      </div>
      <div class="rounded-lg border border-slate-200 bg-white p-4">
        <p class="text-xs text-slate-500">Utilization</p>
        <p class="text-2xl font-bold text-teal-700">{{ utilization?.utilization_rate ?? 0 }}%</p>
      </div>
    </div>

    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-900">Wards</h2>
      <button @click="openWardForm" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Ward</button>
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>

    <div v-else-if="wards.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No wards created</p>
      <p class="mt-1">Create your first ward to begin managing beds.</p>
    </div>

    <div v-else class="space-y-6">
      <div v-for="ward in wards" :key="ward.id" class="rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-semibold text-slate-900">{{ ward.name }}</h3>
              <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-teal-100 text-teal-700">{{ ward.code }}</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
              Floor: {{ ward.floor || '—' }} · Location: {{ ward.location || '—' }}
              · Capacity: {{ ward.capacity ?? '—' }}
              · {{ ward.occupied_beds_count }}/{{ ward.beds_count }} occupied
            </p>
          </div>
          <button @click="openAddBeds(ward)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Add Beds</button>
        </div>

        <div v-if="bedsByWard[ward.id] && bedsByWard[ward.id].length > 0" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
          <div
            v-for="bed in bedsByWard[ward.id]"
            :key="bed.id"
            class="rounded-lg border p-3"
            :class="cleaningStyles[bed.cleaning_status]"
          >
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold text-slate-800">Bed {{ bed.bed_number }}</span>
              <span v-if="bed.is_occupied" class="rounded-full px-2 py-0.5 text-[10px] font-medium bg-blue-700 text-white">OCCUPIED</span>
            </div>
            <p class="mt-1 text-[11px] capitalize text-slate-600">{{ bed.bed_type }} · {{ bed.cleaning_status.replace('_', ' ') }}</p>
            <p v-if="bed.current_patient" class="mt-1 truncate text-[11px] text-slate-700">{{ bed.current_patient.name }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
              <button v-if="!bed.is_occupied" @click="openAssign(bed)" class="rounded border border-teal-300 bg-white px-2 py-0.5 text-[11px] font-medium text-teal-700 hover:bg-teal-50">Assign</button>
              <button v-if="bed.is_occupied" @click="handleDischarge(bed)" class="rounded border border-red-300 bg-white px-2 py-0.5 text-[11px] font-medium text-red-700 hover:bg-red-50">Discharge</button>
              <select
                v-if="!bed.is_occupied"
                :value="bed.cleaning_status"
                @change="handleCleaning(bed, ($event.target as HTMLSelectElement).value as CleaningStatus)"
                class="rounded border border-slate-300 bg-white px-1.5 py-0.5 text-[11px] text-slate-700"
              >
                <option v-for="opt in cleaningOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>
        </div>

        <p v-else class="mt-4 text-center text-xs text-slate-400">No beds in this ward yet.</p>
      </div>
    </div>

    <!-- Add Ward Modal -->
    <div v-if="showWardForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Add Ward</h2>
        <form @submit.prevent="submitWard" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
              <input v-model="wardForm.name" type="text" required placeholder="e.g., East Wing" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Code</label>
              <input v-model="wardForm.code" type="text" required placeholder="e.g., EAST" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Floor</label>
              <input v-model="wardForm.floor" type="text" placeholder="2" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
            <div class="col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Location</label>
              <input v-model="wardForm.location" type="text" placeholder="Building A" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Capacity</label>
            <input v-model.number="wardForm.capacity" type="number" min="0" placeholder="10" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="showWardForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Create Ward' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add Beds Modal -->
    <div v-if="addBedsWard" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-1 text-lg font-semibold text-slate-900">Add Beds</h2>
        <p class="mb-4 text-sm text-slate-500">Ward: {{ addBedsWard.name }}</p>
        <form @submit.prevent="submitAddBeds" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Number of Beds</label>
            <input v-model.number="addBedsCount" type="number" min="1" max="100" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="addBedsWard = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Add Beds</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Assign Modal -->
    <div v-if="assignBedTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-lg">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Assign Patient to Bed {{ assignBedTarget.bed_number }}</h2>
        <form @submit.prevent="submitAssign" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Patient ID</label>
            <input v-model.number="assignPatientId" type="number" min="1" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="flex gap-3">
            <button type="button" @click="assignBedTarget = null" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700">Assign</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
