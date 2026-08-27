<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchNursingDashboard, type NursingDashboardData } from '@/api/dashboard'
import { usePatientContextStore } from '@/stores/patientContext'

const data = ref<NursingDashboardData | null>(null)
const loading = ref(true)
const error = ref('')
const patientCtx = usePatientContextStore()

onMounted(async () => {
  try {
    data.value = await fetchNursingDashboard()
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

      <!-- Stats -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Assigned Patients</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.assigned_patients.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Pending Vitals</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.pending_vitals.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Alerts</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.active_alerts.length }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Assigned Patients -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Assigned Patients</h2>
          </div>
          <div v-if="data.assigned_patients.length === 0" class="p-8 text-center text-sm text-slate-500">
            No assigned patients
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="pt in data.assigned_patients" :key="pt.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ pt.first_name }} {{ pt.last_name }}</p>
                  <p class="text-xs text-slate-500">MRN: {{ pt.mrn }}</p>
                </div>
              </div>
            </li>
          </ul>
        </div>

        <!-- Pending Vitals -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Pending Vitals</h2>
          </div>
          <div v-if="data.pending_vitals.length === 0" class="p-8 text-center text-sm text-slate-500">
            All vitals up to date
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="pt in data.pending_vitals" :key="pt.id" class="px-5 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ pt.first_name }} {{ pt.last_name }}</p>
                <p class="text-xs text-slate-500">MRN: {{ pt.mrn }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Medication Rounds -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Medication Rounds</h2>
          </div>
          <div v-if="data.medication_rounds.length === 0" class="p-8 text-center text-sm text-slate-500">
            No active medication rounds
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="med in data.medication_rounds" :key="med.id" class="px-5 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900">{{ med.name }}</p>
                <p class="text-xs text-slate-500">{{ med.dose }} - {{ med.frequency || 'As needed' }}</p>
              </div>
            </li>
          </ul>
        </div>

        <!-- Admissions/Discharges -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="border-b border-slate-200 p-5">
            <h2 class="text-lg font-semibold text-slate-900">Today's Activity</h2>
          </div>
          <div v-if="data.admissions_discharges.length === 0" class="p-8 text-center text-sm text-slate-500">
            No activity today
          </div>
          <ul v-else class="divide-y divide-slate-200">
            <li v-for="item in data.admissions_discharges" :key="item.id" class="px-5 py-4">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-medium text-slate-900">{{ item.patient_name || 'Patient' }}</p>
                  <p class="text-xs text-slate-500">{{ new Date(item.check_in_time).toLocaleTimeString() }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ item.queue_status }}</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>
