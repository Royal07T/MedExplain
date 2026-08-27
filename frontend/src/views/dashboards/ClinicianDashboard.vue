<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchClinicianDashboard, type ClinicianDashboardData } from '@/api/dashboard'
import { usePatientContextStore } from '@/stores/patientContext'

const data = ref<ClinicianDashboardData | null>(null)
const loading = ref(true)
const error = ref('')
const patientCtx = usePatientContextStore()

onMounted(async () => {
  try {
    data.value = await fetchClinicianDashboard()
    await patientCtx.fetchCurrentContext()
  } catch (e) {
    error.value = 'Failed to load dashboard'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <template v-else-if="data">
      <!-- Patient Context Banner -->
      <div v-if="patientCtx.currentContext" class="rounded-xl border border-teal-200 bg-teal-50 p-4">
        <p class="text-sm text-teal-700">Currently viewing: <strong>{{ patientCtx.currentContext.full_name }}</strong> (MRN: {{ patientCtx.currentContext.mrn }})</p>
      </div>
      <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm text-amber-700">Select a patient from the sidebar to begin working.</p>
      </div>

      <!-- Stats -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Patients Today</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.stats.patients_today }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Encounters Completed</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.stats.encounters_completed }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Pending Reviews</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.stats.pending_reviews }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Today's Appointments -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Today's Appointments</h2>
          </div>
          <div v-if="data.today_appointments.length === 0" class="p-8 text-center text-sm text-slate-500">
            No appointments scheduled for today
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="apt in data.today_appointments" :key="apt.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ apt.patient_name || 'Patient #' + apt.id }}</p>
                  <p class="text-xs text-slate-500">{{ new Date(apt.check_in_time).toLocaleTimeString() }}</p>
                </div>
                <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-medium text-teal-700">{{ apt.status }}</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Waiting Patients -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Waiting Patients</h2>
          </div>
          <div v-if="data.waiting_patients.length === 0" class="p-8 text-center text-sm text-slate-500">
            No patients waiting
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="pt in data.waiting_patients" :key="pt.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ pt.patient_name || 'Patient #' + pt.id }}</p>
                  <p class="text-xs text-slate-500">Checked in {{ new Date(pt.check_in_time).toLocaleTimeString() }}</p>
                </div>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700">Waiting</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Pending Lab Orders -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Pending Lab Orders</h2>
          </div>
          <div v-if="data.pending_labs.length === 0" class="p-8 text-center text-sm text-slate-500">
            No pending lab orders
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="lab in data.pending_labs" :key="lab.id" class="px-5 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ lab.test_name }}</p>
                <p class="text-xs text-slate-500">Ordered {{ new Date(lab.created_at).toLocaleDateString() }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Recent Encounters -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Recent Encounters</h2>
          </div>
          <div v-if="data.recent_encounters.length === 0" class="p-8 text-center text-sm text-slate-500">
            No recent encounters
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="enc in data.recent_encounters" :key="enc.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ enc.patient_name || 'Patient' }}</p>
                  <p class="text-xs text-slate-500">{{ enc.chief_complaint || 'No complaint recorded' }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ enc.queue_status }}</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>
