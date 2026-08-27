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
    error.value = 'Failed to load platform overview'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Platform Overview</h1>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else-if="data">
      <!-- Top Stats -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Organizations</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.organizations.length }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Users</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.users.total }}</p>
          <p class="text-xs text-emerald-600">{{ data.users.verified }} verified</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Active Sessions</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.recent_activity.active_sessions }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">New Users Today</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.recent_activity.new_users_today }}</p>
        </div>
      </div>

      <!-- System Info -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">System Information</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <p class="text-sm text-slate-500">PHP Version</p>
            <p class="text-sm font-semibold text-slate-900">{{ data.system.php_version }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-500">Laravel Version</p>
            <p class="text-sm font-semibold text-slate-900">{{ data.system.laravel_version }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-500">Database Size</p>
            <p class="text-sm font-semibold text-slate-900">{{ data.system.database_size_mb }} MB</p>
          </div>
          <div>
            <p class="text-sm text-slate-500">Uptime</p>
            <p class="text-sm font-semibold text-emerald-600">{{ data.system.uptime }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-500">Response Time</p>
            <p class="text-sm font-semibold text-slate-900">{{ data.system.response_time }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-500">Error Rate</p>
            <p class="text-sm font-semibold text-slate-900">{{ data.system.error_rate }}</p>
          </div>
        </div>
      </div>

      <!-- Organization Stats -->
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Organizations</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
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
              <td colspan="3" class="px-5 py-8 text-center text-sm text-slate-500">No organizations found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
