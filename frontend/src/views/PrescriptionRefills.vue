<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyPrescriptionRefills, createPrescriptionRefill, type CreatePrescriptionRefillRequest, type PrescriptionRefill } from '@/api/prescriptionRefills'

const refills = ref<PrescriptionRefill[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Request form state
const showRequestForm = ref(false)
const requestForm = ref({
  clinician_id: 0,
  medication_name: '',
  dosage: '',
  frequency: '',
  reason: '',
})
const submitting = ref(false)

// Load refills on mount
async function loadRefills() {
  loading.value = true
  error.value = null
  try {
    refills.value = await getMyPrescriptionRefills()
  } catch {
    error.value = 'Failed to load prescription refills'
  } finally {
    loading.value = false
  }
}

// Submit refill request
async function submitRequest() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    await createPrescriptionRefill({
      clinician_id: requestForm.value.clinician_id,
      medication_name: requestForm.value.medication_name,
      dosage: requestForm.value.dosage || undefined,
      frequency: requestForm.value.frequency || undefined,
      reason: requestForm.value.reason,
    })
    
    // Reset form
    showRequestForm.value = false
    requestForm.value = {
      clinician_id: 0,
      medication_name: '',
      dosage: '',
      frequency: '',
      reason: '',
    }
    
    await loadRefills()
    alert('Prescription refill request submitted successfully!')
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to submit refill request'
  } finally {
    submitting.value = false
  }
}

// Format date for display
function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString()
}

// Get status color
function getStatusColor(status: PrescriptionRefill['status']): string {
  switch (status) {
    case 'pending': return 'bg-yellow-100 text-yellow-700'
    case 'approved': return 'bg-green-100 text-green-700'
    case 'denied': return 'bg-red-100 text-red-700'
    case 'filled': return 'bg-blue-100 text-blue-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadRefills()
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Prescription Refills</h1>
        <p class="mt-1 text-sm text-slate-500">
          Request prescription refills from your clinicians.
        </p>
      </div>
      <button
        @click="showRequestForm = true"
        class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700"
      >
        Request Refill
      </button>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Request Form Modal -->
    <div v-if="showRequestForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Request Prescription Refill</h2>
          <button
            @click="showRequestForm = false"
            class="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="submitRequest" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Clinician ID
            </label>
            <input
              v-model.number="requestForm.clinician_id"
              type="number"
              required
              placeholder="Enter clinician ID"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Medication Name
            </label>
            <input
              v-model="requestForm.medication_name"
              type="text"
              required
              placeholder="e.g., Lisinopril 10mg"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Dosage
            </label>
            <input
              v-model="requestForm.dosage"
              type="text"
              placeholder="e.g., 10mg"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Frequency
            </label>
            <input
              v-model="requestForm.frequency"
              type="text"
              placeholder="e.g., Once daily"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Reason for Refill
            </label>
            <textarea
              v-model="requestForm.reason"
              rows="3"
              required
              placeholder="Explain why you need a refill"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click="showRequestForm = false"
              class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? 'Submitting…' : 'Submit Request' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Refills List -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading prescription refills…
    </div>
    
    <div v-else-if="refills.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No refill requests yet</p>
      <p class="mt-1">Request your first prescription refill to get started.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="refill in refills"
        :key="refill.id"
        class="rounded-lg border border-slate-200 p-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="getStatusColor(refill.status)"
              >
                {{ refill.status.toUpperCase() }}
              </span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">
              {{ refill.medication_name }}
            </p>
            
            <p v-if="refill.dosage || refill.frequency" class="mt-1 text-sm text-slate-600">
              {{ [refill.dosage, refill.frequency].filter(Boolean).join(' · ') }}
            </p>
            
            <p v-if="refill.reason" class="mt-1 text-xs text-slate-500">
              Reason: {{ refill.reason }}
            </p>
            
            <p v-if="refill.clinician_notes" class="mt-1 text-xs text-slate-500">
              Clinician notes: {{ refill.clinician_notes }}
            </p>
            
            <p class="mt-2 text-xs text-slate-400">
              Requested: {{ formatDate(refill.requested_at) }}
            </p>
            <p v-if="refill.responded_at" class="text-xs text-slate-400">
              Responded: {{ formatDate(refill.responded_at) }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
