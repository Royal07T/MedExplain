<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyAppointments, bookAppointment, cancelMyAppointment, getMyClinicians, getAvailableClinicians, type Appointment, type ClinicianInfo } from '@/api/appointments'

const appointments = ref<Appointment[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Clinician state
const myClinicians = ref<ClinicianInfo[]>([])
const allClinicians = ref<ClinicianInfo[]>([])
const loadingClinicians = ref(false)
const showAllClinicians = ref(false)

// Booking form state
const showBookingForm = ref(false)
const bookingForm = ref({
  clinician_id: 0,
  chief_complaint: '',
  symptoms: '',
  scheduled_at: '',
  duration_minutes: 30,
})
const submitting = ref(false)

// Load assigned clinicians on mount
async function loadMyClinicians() {
  loadingClinicians.value = true
  try {
    myClinicians.value = await getMyClinicians()
  } catch {
    // Silently fail - dropdown will still work with all clinicians
  } finally {
    loadingClinicians.value = false
  }
}

// Lazy load all org clinicians
async function loadAllClinicians() {
  if (showAllClinicians.value) return
  showAllClinicians.value = true
  try {
    allClinicians.value = await getAvailableClinicians()
  } catch {
    error.value = 'Failed to load clinicians'
  }
}

// Load appointments on mount
async function loadAppointments() {
  loading.value = true
  error.value = null
  try {
    appointments.value = await getMyAppointments()
  } catch {
    error.value = 'Failed to load appointments'
  } finally {
    loading.value = false
  }
}

// Cancel appointment
async function cancelAppointment(appointment: Appointment) {
  if (!confirm(`Are you sure you want to cancel this appointment?`)) return
  
  try {
    await cancelMyAppointment(appointment.id)
    await loadAppointments()
  } catch {
    error.value = 'Failed to cancel appointment'
  }
}

// Format date for display
function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString()
}

// Format date for datetime-local input
function formatDateTimeForInput(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${year}-${month}-${day}T${hours}:${minutes}`
}

// Set default date to tomorrow at 9 AM
function setDefaultDate() {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  tomorrow.setHours(9, 0, 0, 0)
  bookingForm.value.scheduled_at = formatDateTimeForInput(tomorrow)
}

// Submit booking
async function submitBooking() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    await bookAppointment({
      clinician_id: bookingForm.value.clinician_id,
      chief_complaint: bookingForm.value.chief_complaint,
      symptoms: bookingForm.value.symptoms || undefined,
      scheduled_at: new Date(bookingForm.value.scheduled_at).toISOString(),
      duration_minutes: bookingForm.value.duration_minutes,
    })
    
    // Reset form
    showBookingForm.value = false
    showAllClinicians.value = false
    allClinicians.value = []
    bookingForm.value = {
      clinician_id: 0,
      chief_complaint: '',
      symptoms: '',
      scheduled_at: '',
      duration_minutes: 30,
    }
    
    await loadAppointments()
    alert('Appointment booked successfully!')
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to book appointment'
  } finally {
    submitting.value = false
  }
}

// Get status color
function getStatusColor(status: Appointment['status']): string {
  switch (status) {
    case 'scheduled': return 'bg-blue-100 text-blue-700'
    case 'checked_in': return 'bg-green-100 text-green-700'
    case 'in_progress': return 'bg-yellow-100 text-yellow-700'
    case 'completed': return 'bg-gray-100 text-gray-700'
    case 'cancelled': return 'bg-red-100 text-red-700'
    case 'no_show': return 'bg-orange-100 text-orange-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadAppointments()
loadMyClinicians()
setDefaultDate()
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">My Appointments</h1>
        <p class="mt-1 text-sm text-slate-500">
          View and manage your appointments.
        </p>
      </div>
      <button
        @click="showBookingForm = true"
        class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700"
      >
        Book New Appointment
      </button>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Booking Form Modal -->
    <div v-if="showBookingForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Book Appointment</h2>
          <button
            @click="showBookingForm = false"
            class="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="submitBooking" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Select Clinician
            </label>
            <select
              v-model.number="bookingForm.clinician_id"
              required
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            >
              <option :value="0" disabled>Choose a clinician…</option>
              <optgroup v-if="myClinicians.length > 0" label="My Clinicians">
                <option v-for="clinician in myClinicians" :key="clinician.id" :value="clinician.id">
                  {{ clinician.name }} ({{ clinician.email }})
                </option>
              </optgroup>
              <optgroup v-if="showAllClinicians && allClinicians.length > 0" label="All Clinicians in Organization">
                <option v-for="clinician in allClinicians" :key="clinician.id" :value="clinician.id">
                  {{ clinician.name }} ({{ clinician.email }})
                </option>
              </optgroup>
            </select>
            <button
              v-if="!showAllClinicians"
              type="button"
              @click="loadAllClinicians"
              class="mt-2 text-xs text-teal-600 hover:text-teal-700"
            >
              Browse all clinicians in your organization →
            </button>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Scheduled Date & Time
            </label>
            <input
              v-model="bookingForm.scheduled_at"
              type="datetime-local"
              required
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Duration (minutes)
            </label>
            <select
              v-model="bookingForm.duration_minutes"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            >
              <option :value="15">15 minutes</option>
              <option :value="30">30 minutes</option>
              <option :value="45">45 minutes</option>
              <option :value="60">1 hour</option>
              <option :value="90">1.5 hours</option>
              <option :value="120">2 hours</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Chief Complaint
            </label>
            <input
              v-model="bookingForm.chief_complaint"
              type="text"
              required
              placeholder="Reason for visit"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Symptoms
            </label>
            <textarea
              v-model="bookingForm.symptoms"
              rows="3"
              placeholder="Describe symptoms"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click="showBookingForm = false"
              class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? 'Booking…' : 'Book Appointment' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Appointments List -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading appointments…
    </div>
    
    <div v-else-if="appointments.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No appointments yet</p>
      <p class="mt-1">Book your first appointment to get started.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="appointment in appointments"
        :key="appointment.id"
        class="rounded-lg border border-slate-200 p-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="getStatusColor(appointment.status)"
              >
                {{ appointment.status.replace('_', ' ').toUpperCase() }}
              </span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">
              {{ formatDate(appointment.scheduled_at) }}
            </p>
            
            <p v-if="appointment.chief_complaint" class="mt-1 text-sm text-slate-600">
              {{ appointment.chief_complaint }}
            </p>
            
            <p class="mt-2 text-xs text-slate-400">
              Duration: {{ appointment.duration_minutes }} minutes
            </p>
          </div>
          
          <button
            v-if="appointment.status === 'scheduled'"
            @click="cancelAppointment(appointment)"
            class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
