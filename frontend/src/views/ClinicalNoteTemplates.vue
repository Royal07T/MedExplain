<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getClinicalNoteTemplates, createClinicalNoteTemplate, updateClinicalNoteTemplate, deleteClinicalNoteTemplate, type ClinicalNoteTemplate, type CreateClinicalNoteTemplateRequest } from '@/api/clinicalNoteTemplates'

const templates = ref<ClinicalNoteTemplate[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

// Form state
const showForm = ref(false)
const editingTemplate = ref<ClinicalNoteTemplate | null>(null)
const formData = ref<CreateClinicalNoteTemplateRequest>({
  name: '',
  specialty: '',
  note_type: 'progress',
  structure: {},
  default_subjective: '',
  default_objective: '',
  default_assessment: '',
  default_plan: '',
  is_active: true,
})
const submitting = ref(false)

// Load templates
async function loadTemplates() {
  loading.value = true
  error.value = null
  try {
    templates.value = await getClinicalNoteTemplates({ active_only: false })
  } catch {
    error.value = 'Failed to load clinical note templates'
  } finally {
    loading.value = false
  }
}

// Open create form
function openCreateForm() {
  editingTemplate.value = null
  formData.value = {
    name: '',
    specialty: '',
    note_type: 'progress',
    structure: {},
    default_subjective: '',
    default_objective: '',
    default_assessment: '',
    default_plan: '',
    is_active: true,
  }
  showForm.value = true
}

// Open edit form
function openEditForm(template: ClinicalNoteTemplate) {
  editingTemplate.value = template
  formData.value = {
    name: template.name,
    specialty: template.specialty,
    note_type: template.note_type,
    structure: template.structure,
    default_subjective: template.default_subjective || '',
    default_objective: template.default_objective || '',
    default_assessment: template.default_assessment || '',
    default_plan: template.default_plan || '',
    is_active: template.is_active,
  }
  showForm.value = true
}

// Submit form
async function submitForm() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    if (editingTemplate.value) {
      await updateClinicalNoteTemplate(editingTemplate.value.id, formData.value)
    } else {
      await createClinicalNoteTemplate(formData.value)
    }
    
    showForm.value = false
    editingTemplate.value = null
    await loadTemplates()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save template'
  } finally {
    submitting.value = false
  }
}

// Delete template
async function deleteTemplate(id: number) {
  if (!confirm('Are you sure you want to delete this template?')) return

  try {
    await deleteClinicalNoteTemplate(id)
    await loadTemplates()
  } catch {
    error.value = 'Failed to delete template'
  }
}

// Get note type color
function getNoteTypeColor(type: ClinicalNoteTemplate['note_type']): string {
  switch (type) {
    case 'admission': return 'bg-purple-100 text-purple-700'
    case 'progress': return 'bg-blue-100 text-blue-700'
    case 'discharge': return 'bg-green-100 text-green-700'
    case 'consultation': return 'bg-yellow-100 text-yellow-700'
    case 'procedure': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadTemplates()
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Clinical Note Templates</h1>
        <p class="mt-1 text-sm text-slate-500">
          Manage standardized clinical note templates for your organization.
        </p>
      </div>
      <button
        @click="openCreateForm"
        class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700"
      >
        Create Template
      </button>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ editingTemplate ? 'Edit Template' : 'Create Template' }}
          </h2>
          <button
            @click="showForm = false"
            class="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Template Name
              </label>
              <input
                v-model="formData.name"
                type="text"
                required
                placeholder="e.g., General Progress Note"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">
                Specialty
              </label>
              <input
                v-model="formData.specialty"
                type="text"
                required
                placeholder="e.g., Internal Medicine"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Note Type
            </label>
            <select
              v-model="formData.note_type"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
            >
              <option value="admission">Admission Note</option>
              <option value="progress">Progress Note</option>
              <option value="discharge">Discharge Note</option>
              <option value="consultation">Consultation Note</option>
              <option value="procedure">Procedure Note</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Default Subjective
            </label>
            <textarea
              v-model="formData.default_subjective"
              rows="2"
              placeholder="Default subjective section content"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Default Objective
            </label>
            <textarea
              v-model="formData.default_objective"
              rows="2"
              placeholder="Default objective section content"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Default Assessment
            </label>
            <textarea
              v-model="formData.default_assessment"
              rows="2"
              placeholder="Default assessment section content"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Default Plan
            </label>
            <textarea
              v-model="formData.default_plan"
              rows="2"
              placeholder="Default plan section content"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="formData.is_active"
              type="checkbox"
              id="is_active"
              class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
            />
            <label for="is_active" class="text-sm text-slate-700">
              Active
            </label>
          </div>

          <div class="flex gap-3">
            <button
              type="button"
              @click="showForm = false"
              class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? 'Saving…' : 'Save Template' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Templates List -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading templates…
    </div>
    
    <div v-else-if="templates.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No templates yet</p>
      <p class="mt-1">Create your first clinical note template to get started.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div
        v-for="template in templates"
        :key="template.id"
        class="rounded-lg border border-slate-200 p-4"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="getNoteTypeColor(template.note_type)"
              >
                {{ template.note_type.toUpperCase() }}
              </span>
              <span
                v-if="!template.is_active"
                class="rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-600"
              >
                INACTIVE
              </span>
            </div>
            
            <p class="text-sm font-medium text-slate-800">
              {{ template.name }}
            </p>
            
            <p class="mt-1 text-xs text-slate-500">
              Specialty: {{ template.specialty }}
            </p>
            
            <p class="mt-1 text-xs text-slate-400">
              Created: {{ new Date(template.created_at).toLocaleDateString() }}
            </p>
          </div>
          
          <div class="flex gap-2">
            <button
              @click="openEditForm(template)"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            >
              Edit
            </button>
            <button
              @click="deleteTemplate(template.id)"
              class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
