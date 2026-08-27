<script setup lang="ts">
import { onMounted } from 'vue'

import EmptyState from '@/components/EmptyState.vue'
import ErrorState from '@/components/ErrorState.vue'
import LoadingState from '@/components/LoadingState.vue'
import ReportCard from '@/components/ReportCard.vue'
import { useReportsStore } from '@/stores/reports'
import { useRoutePrefix } from '@/composables/useRoutePrefix'

const reports = useReportsStore()
const { routeName } = useRoutePrefix()

onMounted(() => {
  void reports.fetch()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold text-slate-900">Reports</h1>
      <router-link
        :to="{ name: routeName('reports.upload') }"
        class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
      >
        Upload
      </router-link>
    </div>

    <LoadingState v-if="reports.loading" />
    <ErrorState v-else-if="reports.error" :message="reports.error" />
    <EmptyState
      v-else-if="reports.documents.length === 0"
      message="You have not uploaded any reports yet."
      action-label="Upload your first report"
      :action-to="{ name: routeName('reports.upload') }"
    />
    <div v-else class="space-y-3">
      <ReportCard
        v-for="doc in reports.documents"
        :key="doc.id"
        :document="doc"
        @delete="reports.remove"
      />
    </div>
  </div>
</template>