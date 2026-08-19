<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { grantPatientAccess, listPatients, type ClinicianPatient } from '@/api/clinician'
import { getPatientRecord } from '@/api/clinician'
import type { HealthRecord } from '@/types'

const patients = ref<ClinicianPatient[]>([])
const patientsLoading = ref(true)
const patientsError = ref<string | null>(null)

const grantEmail = ref('')
const granting = ref(false)
const grantError = ref<string | null>(null)
const grantMessage = ref<string | null>(null)

const selected = ref<ClinicianPatient | null>(null)
const record = ref<HealthRecord | null>(null)
const recordLoading = ref(false)
const recordError = ref<string | null>(null)

onMounted(loadPatients)

async function loadPatients() {
  patientsLoading.value = true
  patientsError.value = null
  try {
    patients.value = await listPatients()
  } catch {
    patientsError.value = 'Unable to load your patients.'
  } finally {
    patientsLoading.value = false
  }
}

async function submitGrant() {
  const email = grantEmail.value.trim()
  if (!email || granting.value) return

  granting.value = true
  grantError.value = null
  grantMessage.value = null
  try {
    const result = await grantPatientAccess(email)
    grantMessage.value = result.created
      ? `Access granted to ${result.data.name}.`
      : `Access for ${result.data.name} already existed.`
    grantEmail.value = ''
    await loadPatients()
  } catch {
    grantError.value = 'Could not grant access. Check the email address.'
  } finally {
    granting.value = false
  }
}

async function selectPatient(patient: ClinicianPatient) {
  selected.value = patient
  record.value = null
  recordError.value = null
  recordLoading.value = true
  try {
    record.value = await getPatientRecord(patient.id)
  } catch {
    recordError.value = 'Unable to load this patient record.'
  } finally {
    recordLoading.value = false
  }
}

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString()
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Clinician Portal</h1>
      <p class="mt-1 text-sm text-slate-500">
        View the health records of patients you have been granted access to.
        Every access is audited.
      </p>
    </div>

    <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitGrant">
      <div class="min-w-64 flex-1">
        <label for="patient-email" class="mb-1 block text-sm font-medium text-slate-700">
          Grant access to a patient
        </label>
        <input
          id="patient-email"
          v-model="grantEmail"
          type="email"
          required
          placeholder="patient@example.com"
          class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:outline-none"
        />
      </div>
      <button
        type="submit"
        class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="granting || !grantEmail.trim()"
      >
        {{ granting ? 'Granting…' : 'Grant access' }}
      </button>
    </form>

    <p v-if="grantError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
      {{ grantError }}
    </p>
    <p v-if="grantMessage" class="rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm text-teal-700">
      {{ grantMessage }}
    </p>

    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
      <aside>
        <p v-if="patientsError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          {{ patientsError }}
        </p>
        <div
          v-if="patientsLoading"
          class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
        >
          Loading patients…
        </div>
        <div
          v-else-if="patients.length === 0"
          class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
        >
          No patients yet. Grant access above to get started.
        </div>
        <ul v-else class="space-y-2">
          <li v-for="patient in patients" :key="patient.id">
            <button
              type="button"
              class="w-full rounded-lg border px-4 py-3 text-left transition-colors"
              :class="
                selected?.id === patient.id
                  ? 'border-teal-500 bg-teal-50'
                  : 'border-slate-200 bg-white hover:border-teal-300'
              "
              @click="selectPatient(patient)"
            >
              <p class="font-medium text-slate-800">{{ patient.name }}</p>
              <p class="truncate text-xs text-slate-500">{{ patient.email }}</p>
              <p class="mt-1 text-xs text-slate-400">Last lab: {{ formatDate(patient.last_lab_date) }}</p>
            </button>
          </li>
        </ul>
      </aside>

      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p v-if="recordLoading" class="text-sm text-slate-500">Loading patient record…</p>
        <p v-else-if="recordError" class="text-sm text-red-700">{{ recordError }}</p>
        <p v-else-if="!selected" class="text-sm text-slate-500">
          Select a patient to view their health record.
        </p>
        <template v-else-if="record">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ selected.name }}</h2>
            <p class="text-sm text-slate-500">{{ selected.email }}</p>
          </div>

          <div class="mt-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Latest labs</h3>
            <div v-if="record.labs.length === 0" class="mt-2 text-sm text-slate-500">
              No lab results recorded yet.
            </div>
            <ul v-else class="mt-2 divide-y divide-slate-100">
              <li
                v-for="lab in record.labs"
                :key="lab.name"
                class="flex items-center justify-between gap-3 py-2.5"
              >
                <span class="font-medium text-slate-800">{{ lab.name }}</span>
                <span class="text-sm text-slate-600">
                  {{ lab.value ?? '—' }} <span v-if="lab.unit" class="text-slate-400">{{ lab.unit }}</span>
                </span>
              </li>
            </ul>
          </div>

          <div class="mt-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Medications</h3>
            <div v-if="record.medications.length === 0" class="mt-2 text-sm text-slate-500">
              No medications recorded yet.
            </div>
            <ul v-else class="mt-2 space-y-1.5">
              <li v-for="med in record.medications" :key="med.id" class="text-sm text-slate-700">
                {{ med.name }}
                <span v-if="[med.strength, med.dose, med.dosage_form].filter(Boolean).length" class="text-slate-500">
                  — {{ [med.strength, med.dose, med.dosage_form].filter(Boolean).join(' · ') }}
                </span>
                <span v-if="med.frequency" class="text-slate-400">({{ med.frequency }})</span>
              </li>
            </ul>
          </div>

          <div class="mt-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Recent activity</h3>
            <div v-if="record.timeline.length === 0" class="mt-2 text-sm text-slate-500">
              No activity recorded yet.
            </div>
            <ul v-else class="mt-2 space-y-1.5">
              <li
                v-for="(event, index) in record.timeline"
                :key="index"
                class="text-sm text-slate-600"
              >
                {{ event.title }}
                <span v-if="event.description" class="text-slate-400">— {{ event.description }}</span>
                <span class="text-slate-400">({{ formatDate(event.occurred_at) }})</span>
              </li>
            </ul>
          </div>
        </template>
      </section>
    </div>
  </div>
</template>