<script setup lang="ts">
import { onMounted } from 'vue'

import { useMedicationsStore } from '@/stores/medications'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const medications = useMedicationsStore()
const { routeName } = useRoutePrefix()

onMounted(() => {
  void medications.fetch()
})

function details(med: { strength: string | null; dose: string | null; dosage_form: string | null }): string {
  return [med.strength, med.dose, med.dosage_form].filter(Boolean).join(' · ')
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Medications</h1>
      <p class="mt-1 text-sm text-slate-500">
        Medications extracted from your reports.
      </p>
    </div>

    <p v-if="medications.error" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ medications.error }}
    </p>

    <div
      v-if="medications.loading"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      Loading medications…
    </div>

    <div
      v-else-if="medications.medications.length === 0"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      <p class="text-base font-medium text-slate-700">No medications yet</p>
      <p class="mt-1">Medications are extracted automatically when you upload reports.</p>
      <router-link
        :to="{ name: routeName('reports.upload') }"
        class="mt-4 inline-block rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        Upload a report
      </router-link>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="med in medications.medications"
        :key="med.id"
        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="text-base font-semibold text-slate-900">{{ med.name }}</p>
            <p v-if="details(med)" class="mt-1 text-sm text-slate-500">{{ details(med) }}</p>
          </div>
          <span
            v-if="med.frequency"
            class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700"
          >
            {{ med.frequency }}
          </span>
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <div v-if="med.route">
            <dt class="text-xs uppercase tracking-wide text-slate-400">Route</dt>
            <dd class="font-medium text-slate-700">{{ med.route }}</dd>
          </div>
          <div v-if="med.prescriber">
            <dt class="text-xs uppercase tracking-wide text-slate-400">Prescriber</dt>
            <dd class="truncate font-medium text-slate-700" :title="med.prescriber">{{ med.prescriber }}</dd>
          </div>
          <div v-if="med.indications">
            <dt class="text-xs uppercase tracking-wide text-slate-400">Indication</dt>
            <dd class="font-medium text-slate-700">{{ med.indications }}</dd>
          </div>
        </dl>

        <router-link
          v-if="med.medical_document_id"
          :to="{ name: routeName('reports.detail'), params: { id: med.medical_document_id } }"
          class="mt-4 inline-block text-sm font-medium text-teal-700 hover:underline"
        >
          View source report
        </router-link>
      </div>
    </div>
  </div>
</template>