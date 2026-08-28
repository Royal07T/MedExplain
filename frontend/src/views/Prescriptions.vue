<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPatientPrescriptions, createPrescription, updatePrescriptionStatus, type Prescription, type CreatePrescriptionRequest } from '@/api/prescriptions'

const prescriptions = ref<Prescription[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const patientId = ref<number>(1) // This would come from route params or context

// Form state
const showForm = ref(false)
const form = ref<CreatePrescriptionRequest>({
  patient_id: patientId.value,
  medication_id: 0,
  status: 'prescribed',
})
const submitting = ref(false)

// Load data
async function loadPrescriptions() {
  loading.value = true
  error.value = null
  try {
    prescriptions.value = await getPatientPrescriptions(patientId.value)
  } catch {
    error.value = 'Failed to load prescriptions'
  } finally {
    loading.value = false
  }
}

// Form handlers
function openForm() {
  form.value = {
    patient_id: patientId.value,
    medication_id: 0,
    status: 'prescribed',
    notes: '',
  }
  showForm.value = true
}

async function submitForm() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    await createPrescription(form.value)
    showForm.value = false
    await loadPrescriptions()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to create prescription'
  } finally {
    submitting.value = false
  }
}

async function handleUpdateStatus(id: number, status: string) {
  try {
    await updatePrescriptionStatus(id, status)
    await loadPrescriptions()
  } catch {
    error.value = 'Failed to update prescription status'
  }
}

// Format date
function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString()
}

// Get status color
function getStatusColor(status: string): string {
  switch (status) {
    case 'prescribed': return 'bg-blue-100 text-blue-700'
    case 'approved': return 'bg-yellow-100 text-yellow-700'
    case 'dispensed': return 'bg-green-100 text-green-700'
    case 'active': return 'bg-teal-100 text-teal-700'
    case 'discontinued': return 'bg-gray-100 text-gray-700'
    case 'cancelled': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadPrescriptions()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Prescriptions</h1>
      <p class="mt-1 text-sm text-slate-500">
        Manage patient prescriptions and medication orders.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Create Prescription</h2>
          <button @click="showForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Medication ID</label>
            <input v-model.number="form.medication_id" type="number" required placeholder="Enter medication ID" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select v-model="form.status" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="prescribed">Prescribed</option>
              <option value="approved">Approved</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Expiration Date</label>
            <input v-model="form.expires_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Creating…' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Prescriptions List -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-900">Active Prescriptions</h2>
      <button @click="openForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Create Prescription</button>
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>
    
    <div v-else-if="prescriptions.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No prescriptions</p>
      <p class="mt-1">Create a prescription to get started.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div v-for="prescription in prescriptions" :key="prescription.id" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="getStatusColor(prescription.status)">
                {{ prescription.status.toUpperCase() }}
              </span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">
              {{ prescription.medication_name }}
            </p>
            
            <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
              <p>Ordered: {{ formatDate(prescription.ordered_at) }}</p>
              <p>Expires: {{ formatDate(prescription.expires_at) }}</p>
              <p v-if="prescription.dispensed_at">Dispensed: {{ formatDate(prescription.dispensed_at) }}</p>
            </div>
            
            <p v-if="prescription.clinician_name" class="mt-1 text-xs text-slate-400">
              Prescribed by: {{ prescription.clinician_name }}
            </p>
            
            <p v-if="prescription.notes" class="mt-1 text-xs text-slate-500">{{ prescription.notes }}</p>
          </div>
          
          <div class="flex gap-2">
            <button v-if="prescription.status === 'prescribed'" @click="handleUpdateStatus(prescription.id, 'approved')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Approve</button>
            <button v-if="prescription.status === 'approved'" @click="handleUpdateStatus(prescription.id, 'dispensed')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Dispense</button>
            <button v-if="prescription.status === 'dispensed'" @click="handleUpdateStatus(prescription.id, 'active')" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Activate</button>
            <button @click="handleUpdateStatus(prescription.id, 'cancelled')" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
