<script setup lang="ts">
import { ref, onMounted } from 'vue'
import {
  checkSymptoms,
  createMedicationReminder,
  deleteMedicationReminder,
  getMedicationReminders,
  markMedicationReminderTaken,
  toggleMedicationReminder,
  type CreateMedicationReminderRequest,
  type MedicationReminder,
  type SymptomCheckResult,
  type SymptomUrgency,
} from '@/api/virtualAssistant'

const activeTab = ref<'symptoms' | 'medications'>('symptoms')

const urgencyLabels: Record<SymptomUrgency, string> = {
  emergency: 'Seek emergency care',
  urgent: 'Urgent',
  moderate: 'Moderate',
  general: 'General advice',
}

const urgencyStyles: Record<SymptomUrgency, string> = {
  emergency: 'bg-red-600 text-white',
  urgent: 'bg-amber-500 text-white',
  moderate: 'bg-yellow-100 text-yellow-800',
  general: 'bg-teal-100 text-teal-800',
}

// ─── Symptom checker ─────────────────────────────────────
const symptomText = ref('')
const symptomResult = ref<SymptomCheckResult | null>(null)
const checking = ref(false)
const symptomError = ref<string | null>(null)

async function runSymptomCheck() {
  const text = symptomText.value.trim()
  if (!text || checking.value) return
  checking.value = true
  symptomError.value = null
  symptomResult.value = null
  try {
    symptomResult.value = await checkSymptoms(text)
  } catch (err: any) {
    symptomError.value = err?.response?.data?.message || 'Unable to check symptoms right now. Please try again.'
  } finally {
    checking.value = false
  }
}

// ─── Medication reminders ────────────────────────────────
const reminders = ref<MedicationReminder[]>([])
const loadingReminders = ref(false)
const remindersError = ref<string | null>(null)
const showCreateForm = ref(false)
const createError = ref<string | null>(null)
const submitting = ref(false)
const form = ref<CreateMedicationReminderRequest>({
  medication_name: '',
  dose: '',
  route: '',
  frequency: '',
  scheduled_time: '',
  notes: '',
})
const mutationError = ref<string | null>(null)

async function loadReminders() {
  loadingReminders.value = true
  remindersError.value = null
  try {
    reminders.value = await getMedicationReminders()
  } catch {
    remindersError.value = 'Failed to load your medication reminders.'
  } finally {
    loadingReminders.value = false
  }
}

async function submitReminder() {
  if (submitting.value) return
  submitting.value = true
  createError.value = null
  try {
    await createMedicationReminder({
      medication_name: form.value.medication_name,
      dose: form.value.dose || undefined,
      route: form.value.route || undefined,
      frequency: form.value.frequency || undefined,
      scheduled_time: form.value.scheduled_time || undefined,
      notes: form.value.notes || undefined,
    })
    showCreateForm.value = false
    form.value = {
      medication_name: '',
      dose: '',
      route: '',
      frequency: '',
      scheduled_time: '',
      notes: '',
    }
    await loadReminders()
  } catch (err: any) {
    createError.value = err?.response?.data?.message || 'Failed to save this reminder.'
  } finally {
    submitting.value = false
  }
}

async function markTaken(id: number) {
  mutationError.value = null
  try {
    await markMedicationReminderTaken(id)
    await loadReminders()
  } catch {
    mutationError.value = 'Could not mark the dose as taken.'
  }
}

async function toggle(id: number) {
  mutationError.value = null
  try {
    await toggleMedicationReminder(id)
    await loadReminders()
  } catch {
    mutationError.value = 'Could not update this reminder.'
  }
}

async function remove(id: number) {
  mutationError.value = null
  try {
    await deleteMedicationReminder(id)
    await loadReminders()
  } catch {
    mutationError.value = 'Could not delete this reminder.'
  }
}

function formatTaken(value: string | null): string {
  if (!value) return 'Never taken yet'
  return new Date(value).toLocaleString()
}

onMounted(loadReminders)
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Virtual Health Assistant</h1>
      <p class="mt-1 text-sm text-slate-500">
        A self-service companion for your everyday health. Check how urgently you
        should seek care for a symptom, and keep track of your medication doses.
      </p>
    </div>

    <div class="flex gap-2 rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
      <button
        type="button"
        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'symptoms' ? 'bg-teal-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
        @click="activeTab = 'symptoms'"
      >
        Symptom Checker
      </button>
      <button
        type="button"
        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
        :class="activeTab === 'medications' ? 'bg-teal-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
        @click="activeTab = 'medications'"
      >
        Medication Reminders
      </button>
    </div>

    <!-- ─── Symptom Checker ─────────────────────────── -->
    <div v-if="activeTab === 'symptoms'" class="space-y-4">
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">How are you feeling today?</h2>
        <p class="mt-1 text-sm text-slate-500">
          Describe your symptoms in a sentence or two. This provides educational
          triage advice on how urgently you should seek professional care — it
          does not diagnose you.
        </p>

        <form class="mt-4 flex flex-col gap-3 sm:flex-row" @submit.prevent="runSymptomCheck">
          <input
            v-model="symptomText"
            type="text"
            placeholder="e.g., I have a sharp chest pain and trouble breathing"
            class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            :disabled="checking"
          />
          <button
            type="submit"
            class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="checking || !symptomText.trim()"
          >
            {{ checking ? 'Checking…' : 'Check' }}
          </button>
        </form>
      </div>

      <p v-if="symptomError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {{ symptomError }}
      </p>

      <div v-if="symptomResult" class="space-y-4">
        <div
          class="rounded-xl border p-5 shadow-sm"
          :class="symptomResult.urgency === 'emergency' ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white'"
        >
          <span
            class="inline-block rounded-full px-3 py-1 text-xs font-semibold"
            :class="urgencyStyles[symptomResult.urgency]"
          >
            {{ urgencyLabels[symptomResult.urgency] }}
          </span>
          <p class="mt-3 text-sm text-slate-800">{{ symptomResult.message }}</p>

          <div v-if="symptomResult.red_flags.length > 0" class="mt-4 rounded-lg border border-red-200 bg-white p-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-red-700">Red flags noticed</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
              <li v-for="(flag, index) in symptomResult.red_flags" :key="index">{{ flag }}</li>
            </ul>
          </div>

          <div v-if="symptomResult.matched.length > 0" class="mt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Signals detected</h3>
            <ul class="mt-2 space-y-1">
              <li v-for="(hit, index) in symptomResult.matched" :key="index" class="flex items-center gap-2 text-sm text-slate-600">
                <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                {{ hit.symptom }}
              </li>
            </ul>
          </div>

          <p class="mt-4 border-t border-slate-200 pt-3 text-xs text-slate-500">
            {{ symptomResult.disclaimer }}
          </p>
        </div>
      </div>
    </div>

    <!-- ─── Medication Reminders ─────────────────────── -->
    <div v-else-if="activeTab === 'medications'" class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold text-slate-900">Medication Reminders</h2>
          <p class="mt-1 text-sm text-slate-500">
            Keep track of the doses you have taken. Tap a reminder to record a dose.
          </p>
        </div>
        <button
          type="button"
          @click="showCreateForm = true"
          class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700"
        >
          Add Reminder
        </button>
      </div>

      <p v-if="remindersError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {{ remindersError }}
      </p>
      <p v-if="mutationError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
        {{ mutationError }}
      </p>

      <div v-if="loadingReminders" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        Loading medication reminders…
      </div>

      <div v-else-if="reminders.length === 0" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        <p class="text-base font-medium text-slate-700">No reminders yet</p>
        <p class="mt-1">Add a reminder for a medication you take regularly.</p>
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="reminder in reminders"
          :key="reminder.id"
          class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <p class="text-sm font-semibold text-slate-800">{{ reminder.medication_name }}</p>
                <span
                  class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                  :class="reminder.active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                >
                  {{ reminder.active ? 'Active' : 'Paused' }}
                </span>
              </div>

              <p v-if="reminder.dose || reminder.frequency || reminder.scheduled_time" class="mt-1 text-sm text-slate-600">
                {{ [reminder.dose, reminder.frequency, reminder.scheduled_time ? `at ${reminder.scheduled_time}` : ''].filter(Boolean).join(' · ') }}
              </p>
              <p v-if="reminder.notes" class="mt-1 text-xs text-slate-500">{{ reminder.notes }}</p>
              <p class="mt-2 text-xs text-slate-400">Last taken: {{ formatTaken(reminder.last_taken_at) }}</p>
            </div>

            <div class="flex shrink-0 flex-col gap-2">
              <button
                type="button"
                class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-teal-700 disabled:opacity-50"
                :disabled="!reminder.active"
                @click="markTaken(reminder.id)"
              >
                Mark Taken
              </button>
              <div class="flex gap-2">
                <button
                  type="button"
                  class="rounded-lg border border-slate-300 px-3 py-1 text-xs text-slate-600 transition-colors hover:bg-slate-50"
                  @click="toggle(reminder.id)"
                >
                  {{ reminder.active ? 'Pause' : 'Resume' }}
                </button>
                <button
                  type="button"
                  class="rounded-lg border border-red-200 px-3 py-1 text-xs text-red-600 transition-colors hover:bg-red-50"
                  @click="remove(reminder.id)"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create reminder modal -->
    <div v-if="showCreateForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-lg">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Add Medication Reminder</h2>
          <button type="button" class="text-slate-400 hover:text-slate-600" @click="showCreateForm = false">✕</button>
        </div>

        <form class="space-y-4" @submit.prevent="submitReminder">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Medication Name *</label>
            <input
              v-model="form.medication_name"
              type="text"
              required
              placeholder="e.g., Metformin"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Dose</label>
              <input
                v-model="form.dose"
                type="text"
                placeholder="e.g., 500 mg"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Route</label>
              <input
                v-model="form.route"
                type="text"
                placeholder="e.g., oral"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Frequency</label>
              <input
                v-model="form.frequency"
                type="text"
                placeholder="e.g., twice daily"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Scheduled Time</label>
              <input
                v-model="form.scheduled_time"
                type="time"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
              />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
            <textarea
              v-model="form.notes"
              rows="2"
              placeholder="e.g., Take with food"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
            />
          </div>

          <p v-if="createError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {{ createError }}
          </p>

          <div class="flex gap-3">
            <button
              type="button"
              @click="showCreateForm = false"
              class="flex-1 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="flex-1 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? 'Saving…' : 'Save Reminder' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
