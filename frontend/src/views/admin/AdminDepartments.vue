<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchAdminDepartments, createAdminDepartment, deleteAdminDepartment, type AdminDepartment } from '@/api/admin'

const departments = ref<AdminDepartment[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const form = ref({ name: '', code: '', description: '', capacity: '' })
const submitting = ref(false)

onMounted(async () => {
  await loadDepartments()
})

async function loadDepartments() {
  loading.value = true
  error.value = ''
  try {
    departments.value = await fetchAdminDepartments()
  } catch {
    error.value = 'Failed to load departments'
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  submitting.value = true
  try {
    await createAdminDepartment({
      name: form.value.name,
      code: form.value.code,
      description: form.value.description || undefined,
      capacity: form.value.capacity ? parseInt(form.value.capacity) : undefined,
    })
    showForm.value = false
    form.value = { name: '', code: '', description: '', capacity: '' }
    await loadDepartments()
  } catch {
    error.value = 'Failed to create department'
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id: number) {
  if (!confirm('Are you sure you want to delete this department?')) return
  try {
    await deleteAdminDepartment(id)
    await loadDepartments()
  } catch {
    error.value = 'Failed to delete department'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Departments</h1>
      <button
        @click="showForm = !showForm"
        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        {{ showForm ? 'Cancel' : 'Add Department' }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div v-if="showForm" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">New Department</h2>
        <form @submit.prevent="handleSubmit" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Code</label>
            <input v-model="form.code" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
            <input v-model="form.description" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Capacity</label>
            <input v-model="form.capacity" type="number" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="sm:col-span-2">
            <button
              type="submit"
              :disabled="submitting"
              class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
            >
              {{ submitting ? 'Creating...' : 'Create Department' }}
            </button>
          </div>
        </form>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Code</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Staff</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Capacity</th>
              <th class="px-5 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="dept in departments" :key="dept.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ dept.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.code }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.clinicians_count }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ dept.capacity ?? '—' }}</td>
              <td class="px-5 py-3 text-right">
                <button @click="handleDelete(dept.id)" class="text-sm text-red-600 hover:text-red-800">Delete</button>
              </td>
            </tr>
            <tr v-if="departments.length === 0">
              <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No departments found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
