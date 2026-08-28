<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { getLabTestCatalog, createLabTestCatalog, updateLabTestCatalog, deleteLabTestCatalog, type LabTestCatalog, type CreateLabTestCatalogRequest } from '@/api/labCatalog'

const catalog = ref<LabTestCatalog[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const searchQuery = ref('')
const selectedCategory = ref<string>('')

// Form state
const showForm = ref(false)
const editingTest = ref<LabTestCatalog | null>(null)
const form = ref<CreateLabTestCatalogRequest>({
  test_code: '',
  test_name: '',
  specimen_type: 'blood',
  turnaround_hours: 24,
  is_active: true,
})
const submitting = ref(false)

// Load data
async function loadCatalog() {
  loading.value = true
  error.value = null
  try {
    catalog.value = await getLabTestCatalog({
      active_only: true,
      search: searchQuery.value || undefined,
      category: selectedCategory.value || undefined,
    })
  } catch {
    error.value = 'Failed to load lab test catalog'
  } finally {
    loading.value = false
  }
}

// Form handlers
function openForm(test?: LabTestCatalog) {
  editingTest.value = test || null
  form.value = {
    test_code: test?.test_code || '',
    test_name: test?.test_name || '',
    description: test?.description || '',
    category: test?.category || '',
    specimen_type: test?.specimen_type || 'blood',
    container_type: test?.container_type || '',
    turnaround_hours: test?.turnaround_hours || 24,
    cost: test?.cost ?? undefined,
    reference_ranges: test?.reference_ranges || [],
    critical_values: test?.critical_values || [],
    is_active: test?.is_active ?? true,
    notes: test?.notes || '',
  }
  showForm.value = true
}

async function submitForm() {
  if (submitting.value) return
  submitting.value = true
  error.value = null
  try {
    if (editingTest.value) {
      await updateLabTestCatalog(editingTest.value.id, form.value)
    } else {
      await createLabTestCatalog(form.value)
    }
    showForm.value = false
    editingTest.value = null
    await loadCatalog()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save lab test'
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id: number) {
  if (!confirm('Are you sure you want to delete this lab test?')) return
  try {
    await deleteLabTestCatalog(id)
    await loadCatalog()
  } catch {
    error.value = 'Failed to delete lab test'
  }
}

// Get unique categories
const categories = computed<string[]>(() => {
  const cats = new Set<string>()
  for (const test of catalog.value) {
    if (test.category) cats.add(test.category)
  }
  return Array.from(cats)
})

// Initialize
loadCatalog()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Laboratory Management</h1>
      <p class="mt-1 text-sm text-slate-500">
        Manage lab test catalog and reference ranges.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Search and Filter -->
    <div class="flex items-center gap-4">
      <div class="flex-1">
        <input
          v-model="searchQuery"
          @input="loadCatalog"
          type="text"
          placeholder="Search tests by name or code..."
          class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
        />
      </div>
      <select
        v-model="selectedCategory"
        @change="loadCatalog"
        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
      >
        <option value="">All Categories</option>
        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
      </select>
      <button @click="openForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Test</button>
    </div>

    <!-- Lab Tests List -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>
    
    <div v-else-if="catalog.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No lab tests found</p>
      <p class="mt-1">Add your first lab test to get started.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div v-for="test in catalog" :key="test.id" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span class="rounded-full px-2.5 py-1 text-xs font-medium bg-blue-100 text-blue-700">{{ test.test_code }}</span>
              <span v-if="test.category" class="rounded-full px-2.5 py-1 text-xs font-medium bg-slate-100 text-slate-600">{{ test.category }}</span>
              <span v-if="!test.is_active" class="rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-600">INACTIVE</span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">{{ test.test_name }}</p>
            
            <div class="mt-2 grid grid-cols-4 gap-4 text-xs text-slate-500">
              <p>Specimen: {{ test.specimen_type }}</p>
              <p>Turnaround: {{ test.turnaround_hours }}h</p>
              <p v-if="test.cost">Cost: ${{ test.cost }}</p>
              <p v-if="test.container_type">Container: {{ test.container_type }}</p>
            </div>
            
            <p v-if="test.description" class="mt-1 text-xs text-slate-500">{{ test.description }}</p>
            
            <div v-if="test.critical_values && test.critical_values.length > 0" class="mt-2">
              <p class="text-xs font-medium text-red-600">Critical Values Defined</p>
            </div>
          </div>
          
          <div class="flex gap-2">
            <button @click="openForm(test)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
            <button @click="handleDelete(test.id)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">{{ editingTest ? 'Edit Lab Test' : 'Add Lab Test' }}</h2>
          <button @click="showForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Test Code</label>
              <input v-model="form.test_code" type="text" required placeholder="e.g., CBC" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Test Name</label>
              <input v-model="form.test_name" type="text" required placeholder="e.g., Complete Blood Count" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Test description" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Category</label>
              <input v-model="form.category" type="text" placeholder="e.g., Hematology" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Specimen Type</label>
              <select v-model="form.specimen_type" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                <option value="blood">Blood</option>
                <option value="urine">Urine</option>
                <option value="swab">Swab</option>
                <option value="tissue">Tissue</option>
                <option value="fluid">Fluid</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Container Type</label>
              <input v-model="form.container_type" type="text" placeholder="e.g., EDTA" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Turnaround (hours)</label>
              <input v-model.number="form.turnaround_hours" type="number" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Cost ($)</label>
              <input v-model.number="form.cost" type="number" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="is_active" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
            <label for="is_active" class="text-sm text-slate-700">Active</label>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
