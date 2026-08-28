<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPatientProblems, createProblem, updateProblem, deleteProblem, type Problem, type CreateProblemRequest } from '@/api/problems'
import { getPatientAllergies, createAllergy, updateAllergy, deleteAllergy, type Allergy, type CreateAllergyRequest } from '@/api/allergies'

const problems = ref<Problem[]>([])
const allergies = ref<Allergy[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const patientId = ref<number>(1) // This would come from route params or context

// Form states
const showProblemForm = ref(false)
const showAllergyForm = ref(false)
const editingProblem = ref<Problem | null>(null)
const editingAllergy = ref<Allergy | null>(null)

const problemForm = ref<CreateProblemRequest>({
  patient_id: patientId.value,
  icd10_code: '',
  icd10_description: '',
  clinical_notes: '',
  status: 'active',
  onset_date: new Date().toISOString().split('T')[0],
})

const allergyForm = ref<CreateAllergyRequest>({
  patient_id: patientId.value,
  allergen_type: 'drug',
  allergen_name: '',
  reaction_description: '',
  severity: 'mild',
  status: 'active',
  onset_date: new Date().toISOString().split('T')[0],
  notes: '',
})

const submitting = ref(false)

// Load data
async function loadData() {
  loading.value = true
  error.value = null
  try {
    problems.value = await getPatientProblems(patientId.value)
    allergies.value = await getPatientAllergies(patientId.value)
  } catch {
    error.value = 'Failed to load problems and allergies'
  } finally {
    loading.value = false
  }
}

// Problem form handlers
function openProblemForm(problem?: Problem) {
  editingProblem.value = problem || null
  problemForm.value = {
    patient_id: patientId.value,
    icd10_code: problem?.icd10_code || '',
    icd10_description: problem?.icd10_description || '',
    clinical_notes: problem?.clinical_notes || '',
    status: problem?.status || 'active',
    onset_date: problem?.onset_date?.split('T')[0] || new Date().toISOString().split('T')[0],
  }
  showProblemForm.value = true
}

async function submitProblem() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    if (editingProblem.value) {
      await updateProblem(editingProblem.value.id, problemForm.value)
    } else {
      await createProblem(problemForm.value)
    }
    
    showProblemForm.value = false
    editingProblem.value = null
    await loadData()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save problem'
  } finally {
    submitting.value = false
  }
}

async function handleDeleteProblem(id: number) {
  if (!confirm('Are you sure you want to delete this problem?')) return
  try {
    await deleteProblem(id)
    await loadData()
  } catch {
    error.value = 'Failed to delete problem'
  }
}

// Allergy form handlers
function openAllergyForm(allergy?: Allergy) {
  editingAllergy.value = allergy || null
  allergyForm.value = {
    patient_id: patientId.value,
    allergen_type: allergy?.allergen_type || 'drug',
    allergen_name: allergy?.allergen_name || '',
    reaction_description: allergy?.reaction_description || '',
    severity: allergy?.severity || 'mild',
    status: allergy?.status || 'active',
    onset_date: allergy?.onset_date?.split('T')[0] || new Date().toISOString().split('T')[0],
    notes: allergy?.notes || '',
  }
  showAllergyForm.value = true
}

async function submitAllergy() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    if (editingAllergy.value) {
      await updateAllergy(editingAllergy.value.id, allergyForm.value)
    } else {
      await createAllergy(allergyForm.value)
    }
    
    showAllergyForm.value = false
    editingAllergy.value = null
    await loadData()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save allergy'
  } finally {
    submitting.value = false
  }
}

async function handleDeleteAllergy(id: number) {
  if (!confirm('Are you sure you want to delete this allergy?')) return
  try {
    await deleteAllergy(id)
    await loadData()
  } catch {
    error.value = 'Failed to delete allergy'
  }
}

// Status colors
function getProblemStatusColor(status: Problem['status']): string {
  switch (status) {
    case 'active': return 'bg-blue-100 text-blue-700'
    case 'chronic': return 'bg-purple-100 text-purple-700'
    case 'resolved': return 'bg-green-100 text-green-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

function getAllergySeverityColor(severity: Allergy['severity']): string {
  switch (severity) {
    case 'mild': return 'bg-green-100 text-green-700'
    case 'moderate': return 'bg-yellow-100 text-yellow-700'
    case 'severe': return 'bg-orange-100 text-orange-700'
    case 'life_threatening': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

// Initialize
loadData()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Problems & Allergies</h1>
      <p class="mt-1 text-sm text-slate-500">
        Manage patient problem lists and allergy records.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Problem Form Modal -->
    <div v-if="showProblemForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ editingProblem ? 'Edit Problem' : 'Add Problem' }}
          </h2>
          <button @click="showProblemForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitProblem" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">ICD-10 Code</label>
            <input v-model="problemForm.icd10_code" type="text" required placeholder="e.g., I10" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
            <input v-model="problemForm.icd10_description" type="text" required placeholder="e.g., Essential hypertension" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select v-model="problemForm.status" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="active">Active</option>
              <option value="chronic">Chronic</option>
              <option value="resolved">Resolved</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Onset Date</label>
            <input v-model="problemForm.onset_date" type="date" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Clinical Notes</label>
            <textarea v-model="problemForm.clinical_notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showProblemForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Allergy Form Modal -->
    <div v-if="showAllergyForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ editingAllergy ? 'Edit Allergy' : 'Add Allergy' }}
          </h2>
          <button @click="showAllergyForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitAllergy" class="space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Allergen Type</label>
            <select v-model="allergyForm.allergen_type" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="drug">Drug</option>
              <option value="food">Food</option>
              <option value="environmental">Environmental</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Allergen Name</label>
            <input v-model="allergyForm.allergen_name" type="text" required placeholder="e.g., Penicillin" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Reaction Description</label>
            <textarea v-model="allergyForm.reaction_description" rows="2" required placeholder="Describe the reaction" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Severity</label>
            <select v-model="allergyForm.severity" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="mild">Mild</option>
              <option value="moderate">Moderate</option>
              <option value="severe">Severe</option>
              <option value="life_threatening">Life Threatening</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select v-model="allergyForm.status" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
              <option value="active">Active</option>
              <option value="resolved">Resolved</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea v-model="allergyForm.notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showAllergyForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Problems Section -->
    <div class="rounded-xl border border-slate-200 bg-white p-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Problem List</h2>
        <button @click="openProblemForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Problem</button>
      </div>

      <div v-if="loading" class="text-sm text-slate-500">Loading…</div>
      <div v-else-if="!problems || problems.length === 0" class="text-sm text-slate-500">No problems recorded</div>
      <div v-else class="space-y-3">
        <div v-for="problem in problems" :key="problem.id" class="rounded-lg border border-slate-200 p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="getProblemStatusColor(problem.status)">{{ problem.status.toUpperCase() }}</span>
                <span class="text-xs text-slate-500">{{ problem.icd10_code }}</span>
              </div>
              <p class="text-sm font-medium text-slate-800">{{ problem.icd10_description }}</p>
              <p v-if="problem.clinical_notes" class="mt-1 text-xs text-slate-500">{{ problem.clinical_notes }}</p>
              <p class="mt-1 text-xs text-slate-400">Onset: {{ problem.onset_date?.split('T')[0] }}</p>
            </div>
            <div class="flex gap-2">
              <button @click="openProblemForm(problem)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
              <button @click="handleDeleteProblem(problem.id)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Allergies Section -->
    <div class="rounded-xl border border-slate-200 bg-white p-6">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Allergies</h2>
        <button @click="openAllergyForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Add Allergy</button>
      </div>

      <div v-if="loading" class="text-sm text-slate-500">Loading…</div>
      <div v-else-if="!allergies || allergies.length === 0" class="text-sm text-slate-500">No allergies recorded</div>
      <div v-else class="space-y-3">
        <div v-for="allergy in allergies" :key="allergy.id" class="rounded-lg border border-slate-200 p-4">
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="getAllergySeverityColor(allergy.severity)">{{ allergy.severity.toUpperCase() }}</span>
                <span class="text-xs text-slate-500">{{ allergy.allergen_type }}</span>
              </div>
              <p class="text-sm font-medium text-slate-800">{{ allergy.allergen_name }}</p>
              <p class="mt-1 text-xs text-slate-600">{{ allergy.reaction_description }}</p>
              <p v-if="allergy.notes" class="mt-1 text-xs text-slate-500">{{ allergy.notes }}</p>
            </div>
            <div class="flex gap-2">
              <button @click="openAllergyForm(allergy)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
              <button @click="handleDeleteAllergy(allergy.id)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
