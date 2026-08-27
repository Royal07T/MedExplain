<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchSuperAdminDashboard, type SuperAdminDashboardData } from '@/api/dashboard'

const data = ref<SuperAdminDashboardData | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    data.value = await fetchSuperAdminDashboard()
  } catch (e: any) {
    console.error('Dashboard load error:', e)
    error.value = e?.response?.data?.message || e?.message || 'Failed to load dashboard'
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
      <!-- Platform Overview -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Organizations</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.platform_overview.organizations }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Users</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.platform_overview.total_users }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Sessions</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.platform_overview.active_sessions }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- AI Usage -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">AI Usage</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Queries Today</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.ai_usage.queries_today }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Cost Today</span>
              <span class="text-sm font-semibold text-slate-900">${{ data.ai_usage.cost_today.toFixed(2) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Avg Latency</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.ai_usage.avg_latency }}ms</span>
            </div>
          </div>
        </div>

        <!-- System Health -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm p-5">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">System Health</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Uptime</span>
              <span class="text-sm font-semibold text-emerald-600">{{ data.system_health.uptime }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Response Time</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.system_health.response_time }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Error Rate</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.system_health.error_rate }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
