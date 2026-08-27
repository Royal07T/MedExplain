<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminDepartments, type AdminDepartment } from '@/api/admin'

const departments = ref<AdminDepartment[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    departments.value = await fetchAdminDepartments()
  } catch {
    error.value = 'Failed to load organizations'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Organizations</h1>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Code</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Staff</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Capacity</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="dept in departments" :key="dept.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ dept.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.code }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.clinicians_count }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.capacity ?? '—' }}</td>
            </tr>
            <tr v-if="departments.length === 0">
              <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No organizations found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
