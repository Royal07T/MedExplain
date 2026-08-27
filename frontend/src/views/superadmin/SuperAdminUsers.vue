<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchSuperAdminUsers, updateSuperAdminUser, type SuperAdminUser } from '@/api/superadmin'

const users = ref<SuperAdminUser[]>([])
const loading = ref(true)
const error = ref('')
const search = ref('')
const roleFilter = ref('')
const currentPage = ref(1)
const totalPages = ref(1)
const total = ref(0)

onMounted(async () => {
  await loadUsers()
})

async function loadUsers(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params: Record<string, string | number> = { page }
    if (search.value) params.search = search.value
    if (roleFilter.value) params.role = roleFilter.value
    const result = await fetchSuperAdminUsers(params as { role?: string; search?: string })
    users.value = result.data
    currentPage.value = result.current_page
    totalPages.value = result.last_page
    total.value = result.total
  } catch {
    error.value = 'Failed to load users'
  } finally {
    loading.value = false
  }
}

async function handleSearch() {
  await loadUsers(1)
}

function roleBadge(role: string): string {
  const map: Record<string, string> = {
    patient: 'bg-emerald-100 text-emerald-800',
    clinician: 'bg-blue-100 text-blue-800',
    nursing_staff: 'bg-purple-100 text-purple-800',
    admin: 'bg-amber-100 text-amber-800',
    super_admin: 'bg-red-100 text-red-800',
  }
  return map[role] || 'bg-slate-100 text-slate-800'
}

function formatRole(role: string): string {
  return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Users <span class="text-sm font-normal text-slate-500">({{ total }} total)</span></h1>
    </div>

    <!-- Filters -->
    <div class="flex gap-3">
      <input
        v-model="search"
        @keyup.enter="handleSearch"
        placeholder="Search by name or email..."
        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none"
      />
      <select v-model="roleFilter" @change="handleSearch" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none">
        <option value="">All Roles</option>
        <option value="patient">Patient</option>
        <option value="clinician">Clinician</option>
        <option value="nursing_staff">Nursing Staff</option>
        <option value="admin">Admin</option>
        <option value="super_admin">Super Admin</option>
      </select>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">User</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Role</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Joined</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Verified</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div class="h-8 w-8 rounded-full bg-teal-100 flex items-center justify-center">
                    <span class="text-sm font-medium text-teal-600">{{ user.name.charAt(0) }}</span>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-slate-900">{{ user.name }}</p>
                    <p class="text-xs text-slate-500">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', roleBadge(user.role)]">
                  {{ formatRole(user.role) }}
                </span>
              </td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ new Date(user.created_at).toLocaleDateString() }}</td>
              <td class="px-5 py-3">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800">
                  Yes
                </span>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No users found</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-center gap-2">
        <button
          :disabled="currentPage <= 1"
          @click="loadUsers(currentPage - 1)"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-50"
        >
          Previous
        </button>
        <span class="text-sm text-slate-600">Page {{ currentPage }} of {{ totalPages }}</span>
        <button
          :disabled="currentPage >= totalPages"
          @click="loadUsers(currentPage + 1)"
          class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </template>
  </div>
</template>
