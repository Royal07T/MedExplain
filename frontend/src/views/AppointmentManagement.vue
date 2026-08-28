<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getAppointments, updateAppointmentStatus, type Appointment } from '@/api/appointments'
import { listPatients, type ClinicianPatient } from '@/api/clinician'

const patients = ref<ClinicianPatient[]>([])
const selectedPatient = ref<ClinicianPatient | null>(null)
const appointments = ref<Appointment[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

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

// Select patient and load their appointments
async function selectPatient(patient: ClinicianPatient) {
  selectedPatient.value = patient
  await loadPatientAppointments(patient.id)
}

// Load appointments for a patient
async function loadPatientAppointments(patientId: number) {
  loading.value = true
  error.value = null
  try {
    appointments.value = await getAppointments(patientId)
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
    await updateAppointmentStatus(appointment.id, 'cancelled')
    await loadPatientAppointments(selectedPatient.value!.id)
  } catch {
    error.value = 'Failed to cancel appointment'
  }
}

// Complete appointment
async function completeAppointment(appointment: Appointment) {
  try {
    await updateAppointmentStatus(appointment.id, 'completed')
    await loadPatientAppointments(selectedPatient.value!.id)
  } catch {
    error.value = 'Failed to complete appointment'
  }
}

// Format date for display
function formatDate(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleString()
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

// Get acuity color
function getAcuityColor(acuity: Appointment['acuity_level']): string {
  switch (acuity) {
    case 'resuscitation': return 'bg-red-100 text-red-700'
    case 'emergent': return 'bg-orange-100 text-orange-700'
    case 'urgent': return 'bg-yellow-100 text-yellow-700'
    case 'non-urgent': return 'bg-green-100 text-green-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadPatients()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Appointment Management</h1>
      <p class="mt-1 text-sm text-slate-500">
        View and manage appointments for your patients.
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
          No patients available.
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

      <!-- Appointments List -->
      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div v-if="!selectedPatient" class="text-sm text-slate-500">
          Select a patient to view their appointments.
        </div>
        
        <div v-else>
          <div class="border-b border-slate-100 pb-4 mb-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ selectedPatient.name }}</h2>
            <p class="text-sm text-slate-500">{{ selectedPatient.email }}</p>
          </div>

          <div v-if="loading" class="text-sm text-slate-500">
            Loading appointments…
          </div>
          
          <div v-else-if="appointments.length === 0" class="text-sm text-slate-500">
            No appointments found for this patient.
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
                    <span
                      class="rounded-full px-2.5 py-1 text-xs font-medium"
                      :class="getAcuityColor(appointment.acuity_level)"
                    >
                      {{ appointment.acuity_level.replace('_', ' ').toUpperCase() }}
                    </span>
                  </div>
                  
                  <p class="text-sm font-medium text-slate-800">
                    {{ formatDate(appointment.scheduled_at) }}
                  </p>
                  
                  <p v-if="appointment.chief_complaint" class="mt-1 text-sm text-slate-600">
                    {{ appointment.chief_complaint }}
                  </p>
                  
                  <p v-if="appointment.symptoms" class="mt-1 text-xs text-slate-500">
                    {{ appointment.symptoms }}
                  </p>
                  
                  <p class="mt-2 text-xs text-slate-400">
                    Duration, {{ appointment.duration_minutes }} minutes
                  </p>
                </div>
                
                <div class="flex gap-2">
                  <button
                    v-if="appointment.status === 'scheduled'"
                    @click="cancelAppointment(appointment)"
                    class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                  >
                    Cancel
                  </button>
                  <button
                    v-if="appointment.status === 'in_progress'"
                    @click="completeAppointment(appointment)"
                    class="rounded-lg border border-green-300 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50"
                  >
                    Complete
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
