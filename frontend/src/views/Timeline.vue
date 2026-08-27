<script setup lang="ts">
import { onMounted } from 'vue'

import { useHealthStore } from '@/stores/health'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const health = useHealthStore()
const { routeName } = useRoutePrefix()

onMounted(() => {
  void health.fetchTimeline()
})

const eventStyles: Record<string, { dot: string; label: string }> = {
  document_uploaded: { dot: 'bg-teal-500', label: 'Uploaded' },
  document_processed: { dot: 'bg-emerald-500', label: 'Analyzed' },
  analysis_completed: { dot: 'bg-blue-500', label: 'Explanation ready' },
  lab_result: { dot: 'bg-slate-400', label: 'Lab result' },
}

function styleFor(type: string) {
  return eventStyles[type] ?? eventStyles.document_uploaded
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">Health Timeline</h1>
      <p class="mt-1 text-sm text-slate-500">
        A chronological view of your reports, analyses, and lab results.
      </p>
    </div>

    <p v-if="health.timelineError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ health.timelineError }}
    </p>

    <div
      v-if="health.timelineLoading"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      Loading timeline…
    </div>

    <div
      v-else-if="health.timeline.length === 0"
      class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500"
    >
      <p class="text-base font-medium text-slate-700">No events yet</p>
      <p class="mt-1">Upload your first report to start building your health timeline.</p>
      <router-link
        :to="{ name: routeName('reports.upload') }"
        class="mt-4 inline-block rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        Upload a report
      </router-link>
    </div>

    <ol v-else class="space-y-4">
      <li v-for="event in health.timeline" :key="`${event.type}-${event.occurred_at}-${event.document_id}`">
        <div class="flex gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col items-center">
            <span class="mt-1.5 h-3 w-3 rounded-full" :class="styleFor(event.type).dot"></span>
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-semibold text-slate-900">{{ event.title }}</p>
              <span
                class="rounded-full px-2.5 py-1 text-xs font-medium"
                :class="`bg-slate-100 text-slate-600`"
              >
                {{ styleFor(event.type).label }}
              </span>
            </div>
            <p v-if="event.description" class="mt-1 text-sm text-slate-500">{{ event.description }}</p>
            <div class="mt-2 flex items-center justify-between">
              <span class="text-xs text-slate-400">
                {{ new Date(event.occurred_at).toLocaleString() }}
              </span>
              <router-link
                v-if="event.document_id"
                :to="{ name: routeName('reports.detail'), params: { id: event.document_id } }"
                class="text-xs font-medium text-teal-700 hover:underline"
              >
                View report
              </router-link>
            </div>
          </div>
        </div>
      </li>
    </ol>
  </div>
</template>