<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchPatientDashboard, type PatientDashboardData } from '@/api/dashboard'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const data = ref<PatientDashboardData | null>(null)
const loading = ref(true)
const error = ref('')
const { routeName } = useRoutePrefix()

onMounted(async () => {
  try {
    data.value = await fetchPatientDashboard()
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
      <!-- Health Summary -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Lab Results</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.health_summary.total_labs }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Medications</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.health_summary.active_medications }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Recent Documents</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.health_summary.recent_documents }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Upcoming Appointments -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Upcoming Appointments</h2>
          </div>
          <div v-if="data.upcoming_appointments.length === 0" class="p-8 text-center text-sm text-slate-500">
            No upcoming appointments
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="apt in data.upcoming_appointments" :key="apt.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ apt.type || 'Appointment' }}</p>
                  <p class="text-xs text-slate-500">{{ new Date(apt.check_in_time).toLocaleString() }}</p>
                </div>
                <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-medium text-teal-700">{{ apt.status }}</span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Recent Lab Results -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Recent Lab Results</h2>
          </div>
          <div v-if="data.recent_labs.length === 0" class="p-8 text-center text-sm text-slate-500">
            No recent lab results
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="lab in data.recent_labs" :key="lab.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ lab.test_name }}</p>
                  <p class="text-xs text-slate-500">{{ lab.result }} {{ lab.unit || '' }}</p>
                </div>
                <span
                  class="rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="{
                    'bg-emerald-100 text-emerald-700': lab.status === 'normal',
                    'bg-amber-100 text-amber-700': lab.status === 'high' || lab.status === 'low',
                    'bg-red-100 text-red-700': lab.status === 'critical_high' || lab.status === 'critical_low',
                  }"
                >
                  {{ lab.status.replace('_', ' ') }}
                </span>
              </div>
            </li>
          </ul>
        </div>

        <!-- Active Medications -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Active Medications</h2>
          </div>
          <div v-if="data.medications.length === 0" class="p-8 text-center text-sm text-slate-500">
            No active medications
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="med in data.medications" :key="med.id" class="px-5 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ med.name }}</p>
                <p class="text-xs text-slate-500">{{ med.dose }} {{ med.frequency || '' }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Quick Actions -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="mb-4 text-lg font-semibold text-slate-900">Quick Actions</h2>
          <div class="space-y-2">
            <router-link :to="{ name: routeName('reports.upload') }" class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50">
              <span class="rounded-md bg-teal-100 p-2.5 text-teal-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
              </span>
              Upload a report
            </router-link>
            <router-link :to="{ name: routeName('assistant') }" class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50">
              <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
              </span>
              Ask the assistant
            </router-link>
            <router-link :to="{ name: routeName('trends') }" class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50">
              <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
              </span>
              View lab trends
            </router-link>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
