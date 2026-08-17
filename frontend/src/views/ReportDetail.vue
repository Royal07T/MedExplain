<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import * as documentsApi from '@/api/documents'
import Disclaimer from '@/components/Disclaimer.vue'
import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import ResultCard from '@/components/ResultCard.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { usePolling } from '@/composables/usePolling'
import type { Analysis, Document, LabResultStatus } from '@/types'

const route = useRoute()
const id = Number(route.params.id)

const document = ref<Document | null>(null)
const analysis = ref<Analysis | null>(null)
const loading = ref(true)
const loadError = ref<string | null>(null)

const statusLabels: Record<LabResultStatus, string> = {
  normal: 'Normal',
  high: 'High',
  low: 'Low',
  critical_high: 'Critical high',
  critical_low: 'Critical low',
  positive: 'Positive',
  negative: 'Negative',
  unknown: 'Unknown',
}

const statusClasses: Record<LabResultStatus, string> = {
  normal: 'bg-emerald-100 text-emerald-700',
  high: 'bg-amber-100 text-amber-700',
  low: 'bg-amber-100 text-amber-700',
  critical_high: 'bg-red-100 text-red-700',
  critical_low: 'bg-red-100 text-red-700',
  positive: 'bg-amber-100 text-amber-700',
  negative: 'bg-emerald-100 text-emerald-700',
  unknown: 'bg-slate-100 text-slate-600',
}

const { start, stop } = usePolling(refresh, 3000)

async function refresh() {
  if (!document.value) return
  const fresh = await documentsApi.getDocument(id)
  document.value = fresh
  if (fresh.status === 'processed') {
    analysis.value = await documentsApi.getAnalysis(id).catch(() => null)
    stop()
  } else if (fresh.status === 'failed') {
    stop()
  }
}

onMounted(async () => {
  try {
    document.value = await documentsApi.getDocument(id)
    if (document.value.status === 'processed') {
      analysis.value = await documentsApi.getAnalysis(id).catch(() => null)
    } else if (document.value.status === 'processing') {
      await start()
    }
  } catch {
    loadError.value = 'Unable to load this report.'
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => {
  stop()
})
</script>

<template>
  <div class="space-y-6">
    <LoadingState v-if="loading" />
    <ErrorState v-else-if="loadError" :message="loadError" />
    <template v-else-if="document">
      <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h1 class="text-xl font-bold text-slate-900">
              {{ document.original_filename }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">
              {{ document.document_type.replace('_', ' ') }} &middot; uploaded
              {{ new Date(document.created_at).toLocaleDateString() }}
            </p>
          </div>
          <StatusBadge :status="document.status" />
        </div>

        <p
          v-if="document.error_message"
          class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700"
        >
          {{ document.error_message }}
        </p>
      </div>

      <div
        v-if="document.status === 'processing'"
        class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800"
      >
        <p>
          We are analyzing your report. This page refreshes automatically &mdash; you can
          keep this tab open.
        </p>
      </div>

      <div v-if="document.status === 'processed' && analysis" class="space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-slate-900">Summary</h2>
          <p class="mt-2 text-slate-700">{{ analysis.summary }}</p>
        </div>

        <div
          v-if="analysis.concerns.length"
          class="rounded-xl border border-red-200 bg-red-50 p-6"
        >
          <h3 class="font-semibold text-red-800">
            Results worth discussing with your clinician
          </h3>
          <ul class="mt-2 list-inside list-disc text-sm text-red-700">
            <li v-for="concern in analysis.concerns" :key="concern">{{ concern }}</li>
          </ul>
        </div>

        <div class="space-y-3">
          <h3 class="text-lg font-semibold text-slate-900">Explanations</h3>
          <ResultCard
            v-for="item in analysis.items"
            :key="item.test_name"
            :item="item"
          />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="font-semibold text-slate-900">Extracted values</h3>
          <div v-if="analysis.lab_results.length" class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-slate-500">
                  <th class="py-2 pr-4 font-medium">Test</th>
                  <th class="py-2 pr-4 font-medium">Value</th>
                  <th class="py-2 pr-4 font-medium">Unit</th>
                  <th class="py-2 pr-4 font-medium">Reference range</th>
                  <th class="py-2 font-medium">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="result in analysis.lab_results"
                  :key="result.name"
                  class="border-b border-slate-100"
                >
                  <td class="py-2 pr-4 text-slate-800">{{ result.name }}</td>
                  <td class="py-2 pr-4 font-medium text-slate-900">{{ result.value }}</td>
                  <td class="py-2 pr-4 text-slate-500">{{ result.unit ?? '—' }}</td>
                  <td class="py-2 pr-4 text-slate-500">
                    {{ result.reference_range ?? '—' }}
                  </td>
                  <td class="py-2">
                    <span
                      class="rounded-full px-2 py-0.5 text-xs font-medium"
                      :class="statusClasses[result.status]"
                    >
                      {{ statusLabels[result.status] }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-else class="mt-3 text-sm text-slate-500">
            No structured values were extracted from this report.
          </p>
        </div>

        <Disclaimer :text="analysis.disclaimer ?? undefined" />
      </div>

      <EmptyState
        v-else-if="document.status === 'processed' && !analysis"
        message="No analysis is available for this document yet."
      />
    </template>
  </div>
</template>