<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchSuperAdminOrganizations, createSuperAdminOrganization, type SuperAdminOrganization } from '@/api/superadmin'

const organizations = ref<SuperAdminOrganization[]>([])
const loading = ref(true)
const error = ref('')
const showForm = ref(false)
const form = ref({ name: '', slug: '', email: '', phone: '' })
const submitting = ref(false)

onMounted(async () => {
  await loadOrganizations()
})

async function loadOrganizations() {
  loading.value = true
  error.value = ''
  try {
    organizations.value = await fetchSuperAdminOrganizations()
  } catch {
    error.value = 'Failed to load organizations'
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  submitting.value = true
  try {
    await createSuperAdminOrganization({
      name: form.value.name,
      slug: form.value.slug,
      email: form.value.email || undefined,
      phone: form.value.phone || undefined,
    })
    showForm.value = false
    form.value = { name: '', slug: '', email: '', phone: '' }
    await loadOrganizations()
  } catch {
    error.value = 'Failed to create organization'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">Organizations</h1>
      <button
        @click="showForm = !showForm"
        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        {{ showForm ? 'Cancel' : 'Add Organization' }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div v-if="showForm" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">New Organization</h2>
        <form @submit.prevent="handleSubmit" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
            <input v-model="form.name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
            <input v-model="form.slug" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
            <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
            <input v-model="form.phone" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none" />
          </div>
          <div class="sm:col-span-2">
            <button
              type="submit"
              :disabled="submitting"
              class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-50"
            >
              {{ submitting ? 'Creating...' : 'Create Organization' }}
            </button>
          </div>
        </form>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Slug</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Users</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Patients</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="org in organizations" :key="org.id" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm font-medium text-slate-900">{{ org.name }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ org.slug }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ org.users_count }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ org.patients_count }}</td>
              <td class="px-5 py-3">
                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', org.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800']">
                  {{ org.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
            </tr>
            <tr v-if="organizations.length === 0">
              <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-500">No organizations found</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
