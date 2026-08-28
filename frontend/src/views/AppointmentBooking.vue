<script setup lang="ts">
import { ref, computed } from 'vue'
import { createAppointment, type CreateAppointmentRequest, type Appointment } from '@/api/appointments'
import { listPatients, type ClinicianPatient } from '@/api/clinician'

const patients = ref<ClinicianPatient[]>([])
const selectedPatient = ref<ClinicianPatient | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

// Form state
const formData = ref({
  patient_id: 0,
  clinician_id: 0,
  status: 'scheduled' as const,
  acuity_level: 'non-urgent' as const,
  chief_complaint: '',
  symptoms: '',
  scheduled_at: '',
  duration_minutes: 30,
})

const submitting = ref(false)

// Load patients on mount
async function loadPatients() {
  loading.value = true
  error.value = null
  try {
    patients.value = await listPatients()
  } catch {
    error.value = 'Failed to load patients'
  } finally {
    loading.value = false
  }
}

// Select patient
function selectPatient(patient: ClinicianPatient) {
  selectedPatient.value = patient
  formData.value.patient_id = patient.id
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
  formData.value.scheduled_at = formatDateTimeForInput(tomorrow)
}

// Submit appointment
async function submitAppointment() {
  if (!selectedPatient.value || submitting.value) return

  submitting.value = true
  error.value = null

  try {
    const appointmentData: CreateAppointmentRequest = {
      patient_id: formData.value.patient_id,
      clinician_id: formData.value.clinician_id,
      status: formData.value.status,
      acuity_level: formData.value.acuity_level,
      chief_complaint: formData.value.chief_complaint || undefined,
      symptoms: formData.value.symptoms || undefined,
      scheduled_at: new Date(formData.value.scheduled_at).toISOString(),
      duration_minutes: formData.value.duration_minutes,
    }

    await createAppointment(appointmentData)
    
    // Reset form
    selectedPatient.value = null
    formData.value = {
      patient_id: 0,
      clinician_id: 0,
      status: 'scheduled',
      acuity_level: 'non-urgent',
      chief_complaint: '',
      symptoms: '',
      scheduled_at: '',
      duration_minutes: 30,
    }
    setDefaultDate()
    
    alert('Appointment scheduled successfully!')
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to schedule appointment'
  } finally {
    submitting.value = false
  }
}

// Initialize
loadPatients()
setDefaultDate()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Book Appointment</h1>
      <p class="mt-1 text-sm text-slate-500">
        Schedule an appointment with your patients.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
      <!-- Patient Selection -->
      <aside>
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-3">Select Patient</h2>
        
        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
          Loading patients…
        </div>
        
        <div v-else-if="patients.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
          No patients available. Grant access to patients first.
        </div>
        
        <ul v-else class="space-y-2">
          <li v-for="patient in patients" :key="patient.id">
            <button
              type="button"
              class="w-full rounded-lg border px-4 py-3 text-left transition-colors"
              :class="
                selectedPatient?.id === patient.id
                  ? 'border-teal-500 bg-teal-50'
                  : 'border-slate-200 bg-white hover:border-teal-300'
              "
              @click="selectPatient(patient)"
            >
              <p class="font-medium text-slate-800">{{ patient.name }}</p>
              <p class="truncate text-xs text-slate-500">{{ patient.email }}</p>
            </button>
          </li>
        </ul>
      </aside>

      <!-- Appointment Form -->
      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div v-if="!selectedPatient" class="text-sm text-slate-500">
          Select a patient to book an appointment.
        </div>
        
        <form v-else @submit.prevent="submitAppointment" class="space-y-4">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ selectedPatient.name }}</h2>
            <p class="text-sm text-slate-500">{{ selectedPatient.email }}</p>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Scheduled Date & Time
            </label>
            <input
              v-model="formData.scheduled_at"
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
              v-model="formData.duration_minutes"
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
              Acuity Level
            </label>
            <select
              v-model="formData.acuity_level"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            >
              <option value="non-urgent">Non-urgent</option>
              <option value="urgent">Urgent</option>
              <option value="emergent">Emergent</option>
              <option value="resuscitation">Resuscitation</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Chief Complaint
            </label>
            <input
              v-model="formData.chief_complaint"
              type="text"
              placeholder="Reason for visit"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Symptoms
            </label>
            <textarea
              v-model="formData.symptoms"
              rows="3"
              placeholder="Describe symptoms"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <button
            type="submit"
            class="w-full rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="submitting"
          >
            {{ submitting ? 'Scheduling…' : 'Schedule Appointment' }}
          </button>
        </form>
      </section>
    </div>
  </div>
</template>
