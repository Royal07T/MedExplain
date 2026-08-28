<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  getTemplates,
  createTemplate,
  updateTemplate,
  deleteTemplate,
  type NoteType,
  type ClinicalNoteTemplate
} from '@/api/clinicalDocumentation'

const loading = ref(true)
const error = ref<string | null>(null)
const templates = ref<ClinicalNoteTemplate[]>([])

// Modal state
const showEditor = ref(false)
const editingTemplate = ref<ClinicalNoteTemplate | null>(null)
const showDeleteConfirm = ref(false)
const templateToDelete = ref<ClinicalNoteTemplate | null>(null)

// Form state
const formData = ref<{
  name: string
  specialty: string
  note_type: NoteType
  default_subjective: string
  default_objective: string
  default_assessment: string
  default_plan: string
  is_active: boolean
}>({
  name: '',
  specialty: '',
  note_type: 'progress',
  default_subjective: '',
  default_objective: '',
  default_assessment: '',
  default_plan: '',
  is_active: true
})

onMounted(async () => {
  await loadTemplates()
})

async function loadTemplates() {
  loading.value = true
  error.value = null
  try {
    templates.value = await getTemplates()
  } catch (err) {
    error.value = 'Failed to load templates'
    console.error(err)
  } finally {
    loading.value = false
  }
}

function openEditor(template: ClinicalNoteTemplate | null = null) {
  if (template) {
    editingTemplate.value = template
    formData.value = {
      name: template.name,
      specialty: template.specialty || '',
      note_type: template.note_type,
      default_subjective: template.default_subjective || '',
      default_objective: template.default_objective || '',
      default_assessment: template.default_assessment || '',
      default_plan: template.default_plan || '',
      is_active: template.is_active
    }
  } else {
    editingTemplate.value = null
    formData.value = {
      name: '',
      specialty: '',
      note_type: 'progress',
      default_subjective: '',
      default_objective: '',
      default_assessment: '',
      default_plan: '',
      is_active: true
    }
  }
  showEditor.value = true
}

function closeEditor() {
  showEditor.value = false
  editingTemplate.value = null
  formData.value = {
    name: '',
    specialty: '',
    note_type: 'progress',
    default_subjective: '',
    default_objective: '',
    default_assessment: '',
    default_plan: '',
    is_active: true
  }
}

async function saveTemplate() {
  try {
    if (editingTemplate.value) {
      await updateTemplate(editingTemplate.value.id, formData.value)
    } else {
      await createTemplate(formData.value)
    }
    closeEditor()
    await loadTemplates()
  } catch (err) {
    error.value = 'Failed to save template'
    console.error(err)
  }
}

function confirmDelete(template: ClinicalNoteTemplate) {
  templateToDelete.value = template
  showDeleteConfirm.value = true
}

async function deleteTemplateConfirmed() {
  if (!templateToDelete.value) return
  
  try {
    await deleteTemplate(templateToDelete.value.id)
    showDeleteConfirm.value = false
    templateToDelete.value = null
    await loadTemplates()
  } catch (err) {
    error.value = 'Failed to delete template'
    console.error(err)
  }
}

function getNoteTypeColor(type: string): string {
  const colors = {
    admission: 'bg-blue-50 text-blue-700',
    progress: 'bg-green-50 text-green-700',
    discharge: 'bg-purple-50 text-purple-700',
    consultation: 'bg-orange-50 text-orange-700',
    procedure: 'bg-red-50 text-red-700',
    other: 'bg-gray-50 text-gray-700'
  }
  return colors[type as keyof typeof colors] || 'bg-gray-50 text-gray-700'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Clinical Note Templates</h1>
        <p class="mt-1 text-sm text-slate-500">Manage documentation templates for your organization</p>
      </div>
      <button
        @click="openEditor()"
        class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        New Template
      </button>
    </div>

    <!-- Error State -->
    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">
      Loading templates…
    </div>

    <!-- Templates List -->
    <div v-if="!loading" class="space-y-4">
      <div v-if="templates.length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
        No templates found. Create your first template to get started.
      </div>

      <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="template in templates"
          :key="template.id"
          class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
          :class="{ 'opacity-60': !template.is_active }"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="rounded-full px-2 py-1 text-xs font-medium capitalize" :class="getNoteTypeColor(template.note_type)">
                  {{ template.note_type }}
                </span>
                <span v-if="!template.is_active" class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">
                  Inactive
                </span>
              </div>
              <h3 class="mt-2 font-semibold text-slate-900">{{ template.name }}</h3>
              <p v-if="template.specialty" class="mt-1 text-sm text-slate-500">{{ template.specialty }}</p>
            </div>
          </div>

          <div class="mt-4 flex items-center gap-2">
            <button
              @click="openEditor(template)"
              class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Edit
            </button>
            <button
              @click="confirmDelete(template)"
              class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Editor Modal -->
    <div v-if="showEditor" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-slate-900">
            {{ editingTemplate ? 'Edit Template' : 'New Template' }}
          </h2>
          <button
            @click="closeEditor"
            class="rounded p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            ✕
          </button>
        </div>

        <form @submit.prevent="saveTemplate" class="mt-6 space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700">Template Name</label>
              <input
                v-model="formData.name"
                type="text"
                required
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="e.g., General Progress Note"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700">Specialty</label>
              <input
                v-model="formData.specialty"
                type="text"
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="e.g., Cardiology, General Medicine"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Note Type</label>
            <select
              v-model="formData.note_type"
              class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
            >
              <option value="admission">Admission Note</option>
              <option value="progress">Progress Note</option>
              <option value="discharge">Discharge Note</option>
              <option value="consultation">Consultation Note</option>
              <option value="procedure">Procedure Note</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700">Default Subjective</label>
              <textarea
                v-model="formData.default_subjective"
                rows="3"
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="Patient's reported symptoms and concerns..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700">Default Objective</label>
              <textarea
                v-model="formData.default_objective"
                rows="3"
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="Physical examination findings, vital signs, lab results..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700">Default Assessment</label>
              <textarea
                v-model="formData.default_assessment"
                rows="3"
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="Clinical assessment and diagnosis..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700">Default Plan</label>
              <textarea
                v-model="formData.default_plan"
                rows="3"
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                placeholder="Treatment plan, follow-up, medications..."
              />
            </div>
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="formData.is_active"
              type="checkbox"
              id="is_active"
              class="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
            />
            <label for="is_active" class="text-sm text-slate-700">Active</label>
          </div>

          <div class="flex justify-end gap-3 pt-4">
            <button
              type="button"
              @click="closeEditor"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
            >
              {{ editingTemplate ? 'Update' : 'Create' }} Template
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
        <h2 class="text-lg font-semibold text-slate-900">Delete Template</h2>
        <p class="mt-2 text-sm text-slate-600">
          Are you sure you want to delete "{{ templateToDelete?.name }}"? This action cannot be undone.
        </p>
        <div class="mt-6 flex justify-end gap-3">
          <button
            @click="showDeleteConfirm = false; templateToDelete = null"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Cancel
          </button>
          <button
            @click="deleteTemplateConfirmed"
            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
