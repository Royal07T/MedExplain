<script setup lang="ts">
import { computed, onMounted } from 'vue'

import ReportCard from '@/components/ReportCard.vue'
import { useAuth } from '@/composables/useAuth'
import { useReportsStore } from '@/stores/reports'

const { user, isEmailVerified } = useAuth()
const reports = useReportsStore()

const recent = computed(() => reports.documents.slice(0, 3))

onMounted(() => {
  void reports.fetch()
})
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold text-slate-900">
        Welcome{{ user?.name ? `, ${user.name}` : '' }}
      </h1>
      <p class="mt-1 text-sm text-slate-500">
        Upload a medical report and get a plain-language, educational explanation.
      </p>

      <div class="mt-4 flex gap-3">
        <router-link
          :to="{ name: 'reports.upload' }"
          class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
        >
          Upload a report
        </router-link>
        <router-link
          :to="{ name: 'reports' }"
          class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-teal-400"
        >
          View reports
        </router-link>
      </div>

      <div
        v-if="!isEmailVerified"
        class="mt-4 rounded-md bg-amber-50 p-3 text-sm text-amber-800"
      >
        Your email is not verified yet &mdash; please check your inbox.
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-3xl font-bold text-teal-700">{{ reports.documents.length }}</p>
        <p class="text-sm text-slate-500">Uploaded reports</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-3xl font-bold text-teal-700">{{ reports.processedCount }}</p>
        <p class="text-sm text-slate-500">Analyzed</p>
      </div>
    </div>

    <section>
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Recent reports</h2>
        <router-link :to="{ name: 'reports' }" class="text-sm text-teal-700 hover:underline">
          View all
        </router-link>
      </div>

      <div v-if="reports.loading" class="py-8 text-center text-sm text-slate-500">
        Loading…
      </div>
      <div
        v-else-if="recent.length === 0"
        class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
      >
        No reports yet &mdash; upload your first one to get started.
      </div>
      <div v-else class="space-y-3">
        <ReportCard
          v-for="doc in recent"
          :key="doc.id"
          :document="doc"
          @delete="reports.remove"
        />
      </div>
    </section>
  </div>
</template>