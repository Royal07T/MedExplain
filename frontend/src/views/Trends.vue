<script setup lang="ts">
import { computed, onMounted } from 'vue'

import LineChart from '@/components/LineChart.vue'
import { useHealthStore } from '@/stores/health'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const health = useHealthStore()
const { routeName } = useRoutePrefix()

onMounted(async () => {
  await health.fetchTestNames()
  if (health.selectedTest) {
    await health.fetchTrend(health.selectedTest)
  }
})

function onSelect(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  if (value) void health.fetchTrend(value)
}

const chartPoints = computed(() =>
  (health.trend?.series ?? []).map((point) => ({
    label: point.date ? new Date(point.date).toLocaleDateString() : '?',
    value: parseFloat(point.value.replace(',', '.')),
  })),
)

const range = computed(() => {
  const refs = health.trend?.series
    .map((s) => s.reference_range)
    .filter((r): r is string => !!r) ?? []
  const parse = (text: string): [number, number] | null => {
    const match = text.match(/(\d+(?:[.,]\d+)?)\s*[-–—~]\s*(\d+(?:[.,]\d+)?)/)
    if (!match) return null
    return [parseFloat(match[1]), parseFloat(match[2])]
  }
  const first = refs.length ? parse(refs[0]) : null
  return first ?? null
})

const statusClasses: Record<string, string> = {
  within_range: 'bg-emerald-100 text-emerald-700',
  above_range: 'bg-red-100 text-red-700',
  below_range: 'bg-amber-100 text-amber-700',
  positive: 'bg-red-100 text-red-700',
  negative: 'bg-emerald-100 text-emerald-700',
  unknown: 'bg-slate-100 text-slate-600',
}

function statusLabel(status: string): string {
  return status.replaceAll('_', ' ').replace(/^\w/, (c) => c.toUpperCase())
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Lab Trends</h1>
        <p class="mt-1 text-sm text-slate-500">
          See how a test has changed across your reports.
        </p>
      </div>

      <label class="flex flex-col gap-1">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Test</span>
        <select
          :value="health.selectedTest ?? ''"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-teal-500 focus:outline-none"
          @change="onSelect"
        >
          <option v-if="health.testNames.length === 0" value="">No tests available</option>
          <option v-for="test in health.testNames" :key="test.name" :value="test.name">
            {{ test.name }}{{ test.count > 1 ? ` (${test.count})` : '' }}
          </option>
        </select>
      </label>
    </div>

    <p v-if="health.namesError || health.trendError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ health.namesError ?? health.trendError }}
    </p>

    <div
      v-if="health.trendLoading"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      Loading trends…
    </div>

    <div
      v-else-if="health.trend && health.trend.series.length > 0"
      class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
    >
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">{{ health.trend.test }}</h2>
        <p class="text-sm text-slate-500">
          {{ health.trend.series.length }} {{ health.trend.series.length === 1 ? 'measurement' : 'measurements' }}
          {{ health.trend.unit ? `in ${health.trend.unit}` : '' }}
        </p>
      </div>

      <LineChart
        :points="chartPoints"
        :unit="health.trend.unit"
        :ref-low="range ? range[0] : null"
        :ref-high="range ? range[1] : null"
      />

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase text-slate-500">
              <th class="py-2 pr-4">Date</th>
              <th class="py-2 pr-4">Value</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2">Report</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="point in [...health.trend.series].reverse()"
              :key="`${point.date}-${point.document_id}`"
              class="hover:bg-slate-50"
            >
              <td class="py-3 pr-4 text-slate-700">
                {{ point.date ? new Date(point.date).toLocaleDateString() : '—' }}
              </td>
              <td class="py-3 pr-4 font-medium text-slate-900">
                {{ point.value }}{{ health.trend.unit ? ` ${health.trend.unit}` : '' }}
              </td>
              <td class="py-3 pr-4">
                <span
                  class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="statusClasses[point.status] ?? statusClasses.unknown"
                >
                  {{ statusLabel(point.status) }}
                </span>
              </td>
              <td class="py-3">
                <router-link
                  v-if="point.document_id"
                  :to="{ name: routeName('reports.detail'), params: { id: point.document_id } }"
                  class="text-teal-700 hover:underline"
                >
                  {{ point.document_filename ?? 'View report' }}
                </router-link>
                <span v-else class="text-slate-400">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      v-else
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      <p class="text-base font-medium text-slate-700">No trends yet</p>
      <p class="mt-1">Upload and analyze reports to start tracking lab values over time.</p>
      <router-link
        :to="{ name: routeName('reports.upload') }"
        class="mt-4 inline-block rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        Upload a report
      </router-link>
    </div>
  </div>
</template>