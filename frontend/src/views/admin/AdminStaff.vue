<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminStaff, type AdminStaffMember } from '@/api/admin'

const staff = ref<AdminStaffMember[]>([])
const loading = ref(true)
const error = ref('')
const filter = ref('all')

onMounted(async () => {
  await loadStaff()
})

async function loadStaff() {
  loading.value = true
  error.value = ''
  try {
    staff.value = await fetchAdminStaff()
  } catch {
    error.value = 'Failed to load staff'
  } finally {
    loading.value = false
  }
}

const filteredStaff = ref<AdminStaffMember[]>([])

function applyFilter() {
  if (filter.value === 'all') {
    filteredStaff.value = staff.value
  } else {
    filteredStaff.value = staff.value.filter(s => s.role === filter.value)
  }
}

import { watch } from 'vue'
watch([staff, filter], () => applyFilter(), { immediate: true })

function roleBadge(role: string): string {
  const map: Record<string, string> = {
    clinician: 'bg-blue-100 text-blue-800',
    nursing_staff: 'bg-purple-100 text-purple-800',
    admin: 'bg-amber-100 text-amber-800',
  }
  return map[role] || 'bg-slate-100 text-slate-800'
}

function formatRole(role: string): string {
  return role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Staff Management</h1>
      <select v-model="filter" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none">
        <option value="all">All Roles</option>
        <option value="clinician">Clinicians</option>
        <option value="nursing_staff">Nursing Staff</option>
        <option value="admin">Admins</option>
      </select>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="member in filteredStaff" :key="member.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-teal-100 flex items-center justify-center">
              <span class="text-sm font-medium text-teal-600">{{ member.name.charAt(0) }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium text-slate-900">{{ member.name }}</p>
              <p class="truncate text-xs text-slate-500">{{ member.email }}</p>
            </div>
          </div>
          <div class="mt-3 flex items-center justify-between">
            <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', roleBadge(member.role)]">
              {{ formatRole(member.role) }}
            </span>
            <span class="text-xs text-slate-500">{{ member.departments_count }} dept{{ member.departments_count !== 1 ? 's' : '' }}</span>
          </div>
        </div>
        <div v-if="filteredStaff.length === 0" class="sm:col-span-2 lg:col-span-3 text-center py-12 text-slate-500">
          No staff members found
        </div>
      </div>
    </template>
  </div>
</template>
