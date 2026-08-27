<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchSuperAdminSystemHealth, type SuperAdminSystemHealth } from '@/api/superadmin'

const data = ref<SuperAdminSystemHealth | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    data.value = await fetchSuperAdminSystemHealth()
  } catch {
    error.value = 'Failed to load system health'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">System Health</h1>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else-if="data">
      <!-- Health Indicators -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Uptime</p>
          <p class="mt-2 text-3xl font-bold text-emerald-600">{{ data.system.uptime }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Response Time</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.system.response_time }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Error Rate</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.system.error_rate }}</p>
        </div>
      </div>

      <!-- System Details -->
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">System Details</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">PHP Version</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.system.php_version }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Laravel Version</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.system.laravel_version }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Database Size</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.system.database_size_mb }} MB</span>
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900 mb-4">User Statistics</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Total Users</span>
              <span class="text-sm font-semibold text-slate-900">{{ data.users.total }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Verified</span>
              <span class="text-sm font-semibold text-emerald-600">{{ data.users.verified }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Unverified</span>
              <span class="text-sm font-semibold text-amber-600">{{ data.users.unverified }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Recent Activity</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-sm text-slate-500">New Users Today</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ data.recent_activity.new_users_today }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Active Sessions</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ data.recent_activity.active_sessions }}</p>
          </div>
        </div>
      </div>

      <!-- Organization Breakdown -->
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Organization Health</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Organization</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Users</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Patients</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="org in data.organizations" :key="org.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ org.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ org.users_count }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ org.patients_count }}</td>
            </tr>
            <tr v-if="data.organizations.length === 0">
              <td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No organizations</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
