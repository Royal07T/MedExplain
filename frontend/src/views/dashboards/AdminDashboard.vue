<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminDashboard, type AdminDashboardData } from '@/api/dashboard'

const data = ref<AdminDashboardData | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    data.value = await fetchAdminDashboard()
  } catch (e) {
    error.value = 'Failed to load dashboard'
  } finally {
    loading.value = false
  }
})

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount)
}
</script>

<template>
  <div class="space-y-6">
    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <template v-else-if="data">
      <!-- Stats Grid -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Patients</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.patient_count.total }}</p>
          <p class="text-xs text-emerald-600">+{{ data.patient_count.new_today }} today</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Today's Appointments</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.appointments.scheduled }}</p>
          <p class="text-xs text-slate-500">{{ data.appointments.completed }} completed</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Staff On Duty</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.staff.on_duty }}</p>
          <p class="text-xs text-slate-500">{{ data.staff.available }} available</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Revenue Today</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(data.billing.revenue) }}</p>
          <p class="text-xs text-amber-600">{{ formatCurrency(data.billing.outstanding) }} outstanding</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Appointments Breakdown -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Appointments Today</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Scheduled</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.appointments.scheduled }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Completed</span>
              <span class="text-sm font-semibold text-emerald-600">{{ data.appointments.completed }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">No Shows</span>
              <span class="text-sm font-semibold text-red-600">{{ data.appointments.no_shows }}</span>
            </div>
          </div>
        </div>

        <!-- Operations -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">Operations</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Lab Orders Pending</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.laboratory.pending }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Lab Orders Completed</span>
              <span class="text-sm font-semibold text-emerald-600">{{ data.laboratory.completed }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Pharmacy Pending</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.pharmacy.pending }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Pharmacy Filled</span>
              <span class="text-sm font-semibold text-emerald-600">{{ data.pharmacy.filled }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
