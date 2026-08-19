<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { getHealthRecord } from '@/api/health'
import type { HealthRecord } from '@/types'

const record = ref<HealthRecord | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    record.value = await getHealthRecord()
  } catch {
    error.value = 'Unable to load your health record.'
  } finally {
    loading.value = false
  }
})

function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Date(value).toLocaleDateString()
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">My Health Record</h1>
      <p class="mt-1 text-sm text-slate-500">
        A consolidated view of your labs, medications, and recent activity.
      </p>
    </div>

    <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      Loading your health record…
    </div>

    <template v-else-if="record">
      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Profile</h2>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">Name</p>
            <p class="font-medium text-slate-800">{{ record.profile.name }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">Email</p>
            <p class="font-medium text-slate-800">{{ record.profile.email }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">Date of birth</p>
            <p class="font-medium text-slate-800">{{ formatDate(record.profile.date_of_birth) }}</p>
          </div>
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">Gender</p>
            <p class="font-medium text-slate-800">{{ record.profile.gender ?? '—' }}</p>
          </div>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Latest labs</h2>
          <router-link
            :to="{ name: 'trends' }"
            class="text-sm font-medium text-teal-700 hover:underline"
          >
            View trends
          </router-link>
        </div>

        <div v-if="record.labs.length === 0" class="mt-4 text-sm text-slate-500">
          No lab results recorded yet.
        </div>
        <div v-else class="mt-3 overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                <th class="pb-2 pr-4 font-medium">Test</th>
                <th class="pb-2 pr-4 font-medium">Value</th>
                <th class="pb-2 pr-4 font-medium">Reference range</th>
                <th class="pb-2 font-medium">Collected</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lab in record.labs" :key="lab.name" class="border-b border-slate-100">
                <td class="py-2.5 pr-4 font-medium text-slate-800">{{ lab.name }}</td>
                <td class="py-2.5 pr-4 text-slate-700">
                  {{ lab.value ?? '—' }}
                  <span v-if="lab.unit" class="text-slate-400">{{ lab.unit }}</span>
                </td>
                <td class="py-2.5 pr-4 text-slate-500">{{ lab.reference_range ?? '—' }}</td>
                <td class="py-2.5 text-slate-500">{{ formatDate(lab.last_collected_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Medications</h2>
          <router-link
            :to="{ name: 'medications' }"
            class="text-sm font-medium text-teal-700 hover:underline"
          >
            View all
          </router-link>
        </div>

        <div v-if="record.medications.length === 0" class="mt-4 text-sm text-slate-500">
          No medications recorded yet.
        </div>
        <ul v-else class="mt-3 space-y-2">
          <li
            v-for="med in record.medications"
            :key="med.id"
            class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 px-4 py-3"
          >
            <div>
              <p class="font-medium text-slate-800">{{ med.name }}</p>
              <p class="text-sm text-slate-500">
                {{ [med.strength, med.dose, med.dosage_form].filter(Boolean).join(' · ') || '—' }}
              </p>
            </div>
            <span
              v-if="med.frequency"
              class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700"
            >
              {{ med.frequency }}
            </span>
          </li>
        </ul>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Recent activity</h2>
          <router-link
            :to="{ name: 'timeline' }"
            class="text-sm font-medium text-teal-700 hover:underline"
          >
            View timeline
          </router-link>
        </div>

        <div v-if="record.timeline.length === 0" class="mt-4 text-sm text-slate-500">
          No activity recorded yet.
        </div>
        <ul v-else class="mt-3 space-y-3">
          <li v-for="(event, index) in record.timeline" :key="index" class="flex gap-3">
            <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-teal-500"></div>
            <div class="min-w-0">
              <p class="text-sm font-medium text-slate-800">{{ event.title }}</p>
              <p v-if="event.description" class="truncate text-xs text-slate-500">
                {{ event.description }}
              </p>
              <p class="text-xs text-slate-400">{{ formatDate(event.occurred_at) }}</p>
            </div>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>