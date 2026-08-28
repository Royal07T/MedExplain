<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getPatientVitalSigns, createVitalSign, updateVitalSign, deleteVitalSign, type VitalSign, type CreateVitalSignRequest } from '@/api/vitalSigns'

const vitalSigns = ref<VitalSign[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const patientId = ref<number>(1) // This would come from route params or context

// Form state
const showForm = ref(false)
const editingVitalSign = ref<VitalSign | null>(null)
const form = ref<CreateVitalSignRequest>({
  patient_id: patientId.value,
  temperature_unit: 'C',
  weight_unit: 'kg',
  height_unit: 'cm',
})
const submitting = ref(false)

// Load data
async function loadVitalSigns() {
  loading.value = true
  error.value = null
  try {
    vitalSigns.value = await getPatientVitalSigns(patientId.value)
  } catch {
    error.value = 'Failed to load vital signs'
  } finally {
    loading.value = false
  }
}

// Form handlers
function openForm(vitalSign?: VitalSign) {
  editingVitalSign.value = vitalSign || null
  form.value = {
    patient_id: patientId.value,
    encounter_id: vitalSign?.encounter_id ?? undefined,
    temperature: vitalSign?.temperature ?? undefined,
    temperature_unit: vitalSign?.temperature_unit || 'C',
    heart_rate: vitalSign?.heart_rate ?? undefined,
    blood_pressure_systolic: vitalSign?.blood_pressure_systolic ?? undefined,
    blood_pressure_diastolic: vitalSign?.blood_pressure_diastolic ?? undefined,
    respiratory_rate: vitalSign?.respiratory_rate ?? undefined,
    oxygen_saturation: vitalSign?.oxygen_saturation ?? undefined,
    weight: vitalSign?.weight ?? undefined,
    weight_unit: vitalSign?.weight_unit || 'kg',
    height: vitalSign?.height ?? undefined,
    height_unit: vitalSign?.height_unit || 'cm',
    pain_score: vitalSign?.pain_score ?? undefined,
    notes: vitalSign?.notes ?? undefined,
    recorded_at: vitalSign?.recorded_at?.split('T')[0] || new Date().toISOString().split('T')[0],
  }
  showForm.value = true
}

async function submitForm() {
  if (submitting.value) return

  submitting.value = true
  error.value = null

  try {
    if (editingVitalSign.value) {
      await updateVitalSign(editingVitalSign.value.id, form.value)
    } else {
      await createVitalSign(form.value)
    }
    
    showForm.value = false
    editingVitalSign.value = null
    await loadVitalSigns()
  } catch (err: any) {
    error.value = err?.response?.data?.message || 'Failed to save vital signs'
  } finally {
    submitting.value = false
  }
}

async function handleDelete(id: number) {
  if (!confirm('Are you sure you want to delete this vital sign record?')) return
  try {
    await deleteVitalSign(id)
    await loadVitalSigns()
  } catch {
    error.value = 'Failed to delete vital signs'
  }
}

// Format date
function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleString()
}

// Initialize
loadVitalSigns()
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Vital Signs</h1>
      <p class="mt-1 text-sm text-slate-500">
        Track and manage patient vital signs.
      </p>
    </div>

    <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-2xl rounded-xl bg-white p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">
            {{ editingVitalSign ? 'Edit Vital Signs' : 'Record Vital Signs' }}
          </h2>
          <button @click="showForm = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Temperature ({{ form.temperature_unit }})</label>
              <div class="flex gap-2">
                <input v-model.number="form.temperature" type="number" step="0.1" placeholder="36.5" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
                <select v-model="form.temperature_unit" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                  <option value="C">°C</option>
                  <option value="F">°F</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Heart Rate (bpm)</label>
              <input v-model.number="form.heart_rate" type="number" placeholder="72" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Blood Pressure (Systolic/Diastolic)</label>
              <div class="flex gap-2">
                <input v-model.number="form.blood_pressure_systolic" type="number" placeholder="120" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
                <input v-model.number="form.blood_pressure_diastolic" type="number" placeholder="80" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Respiratory Rate (/min)</label>
              <input v-model.number="form.respiratory_rate" type="number" placeholder="16" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Oxygen Saturation (%)</label>
              <input v-model.number="form.oxygen_saturation" type="number" max="100" placeholder="98" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Weight ({{ form.weight_unit }})</label>
              <div class="flex gap-2">
                <input v-model.number="form.weight" type="number" step="0.1" placeholder="70" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
                <select v-model="form.weight_unit" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                  <option value="kg">kg</option>
                  <option value="lb">lb</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Height ({{ form.height_unit }})</label>
              <div class="flex gap-2">
                <input v-model.number="form.height" type="number" step="0.1" placeholder="175" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
                <select v-model="form.height_unit" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none">
                  <option value="cm">cm</option>
                  <option value="in">in</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Pain Score (0-10)</label>
              <input v-model.number="form.pain_score" type="number" min="0" max="10" placeholder="0" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Recorded Date</label>
            <input v-model="form.recorded_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Additional notes" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none" />
          </div>

          <div class="flex gap-3">
            <button type="button" @click="showForm = false" class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Vital Signs List -->
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-slate-900">History</h2>
      <button @click="openForm()" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Record Vital Signs</button>
    </div>

    <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      Loading…
    </div>
    
    <div v-else-if="!vitalSigns || vitalSigns.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
      <p class="text-base font-medium text-slate-700">No vital signs recorded</p>
      <p class="mt-1">Start by recording the first set of vital signs.</p>
    </div>
    
    <div v-else class="space-y-3">
      <div v-for="vitalSign in vitalSigns" :key="vitalSign.id" class="rounded-lg border border-slate-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1">
            <p class="text-xs text-slate-400 mb-2">{{ formatDate(vitalSign.recorded_at) }}</p>
            
            <div class="grid grid-cols-4 gap-4 text-sm">
              <div v-if="vitalSign.temperature">
                <p class="text-slate-500">Temperature</p>
                <p class="font-medium text-slate-800">{{ vitalSign.temperature }}°{{ vitalSign.temperature_unit }}</p>
              </div>
              
              <div v-if="vitalSign.heart_rate">
                <p class="text-slate-500">Heart Rate</p>
                <p class="font-medium text-slate-800">{{ vitalSign.heart_rate }} bpm</p>
              </div>
              
              <div v-if="vitalSign.blood_pressure_systolic && vitalSign.blood_pressure_diastolic">
                <p class="text-slate-500">Blood Pressure</p>
                <p class="font-medium text-slate-800">{{ vitalSign.blood_pressure_systolic }}/{{ vitalSign.blood_pressure_diastolic }}</p>
              </div>
              
              <div v-if="vitalSign.oxygen_saturation">
                <p class="text-slate-500">SpO2</p>
                <p class="font-medium text-slate-800">{{ vitalSign.oxygen_saturation }}%</p>
              </div>
              
              <div v-if="vitalSign.respiratory_rate">
                <p class="text-slate-500">Resp. Rate</p>
                <p class="font-medium text-slate-800">{{ vitalSign.respiratory_rate }}/min</p>
              </div>
              
              <div v-if="vitalSign.weight">
                <p class="text-slate-500">Weight</p>
                <p class="font-medium text-slate-800">{{ vitalSign.weight }} {{ vitalSign.weight_unit }}</p>
              </div>
              
              <div v-if="vitalSign.height">
                <p class="text-slate-500">Height</p>
                <p class="font-medium text-slate-800">{{ vitalSign.height }} {{ vitalSign.height_unit }}</p>
              </div>
              
              <div v-if="vitalSign.bmi">
                <p class="text-slate-500">BMI</p>
                <p class="font-medium text-slate-800">{{ vitalSign.bmi }}</p>
              </div>
              
              <div v-if="vitalSign.pain_score !== null">
                <p class="text-slate-500">Pain Score</p>
                <p class="font-medium text-slate-800">{{ vitalSign.pain_score }}/10</p>
              </div>
            </div>
            
            <p v-if="vitalSign.notes" class="mt-2 text-xs text-slate-500">{{ vitalSign.notes }}</p>
          </div>
          
          <div class="flex gap-2">
            <button @click="openForm(vitalSign)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</button>
            <button @click="handleDelete(vitalSign.id)" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
