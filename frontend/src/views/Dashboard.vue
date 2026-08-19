<script setup lang="ts">
import { computed, onMounted } from 'vue'

import { useReportsStore } from '@/stores/reports'

const reports = useReportsStore()

const recent = computed(() => reports.documents.slice(0, 3))

const pendingCount = computed(
  () => reports.documents.filter((d) => d.status === 'processing' || d.status === 'uploaded').length,
)

const failedCount = computed(() => reports.documents.filter((d) => d.status === 'failed').length)

onMounted(() => {
  void reports.fetch()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Stats Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500">Total Reports</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ reports.documents.length }}</p>
          </div>
          <span class="rounded-full bg-teal-100 p-3 text-teal-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
          </span>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500">Analyzed</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ reports.processedCount }}</p>
          </div>
          <span class="rounded-full bg-emerald-100 p-3 text-emerald-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </span>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500">Processing</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ pendingCount }}</p>
          </div>
          <span class="rounded-full bg-amber-100 p-3 text-amber-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </span>
        </div>
      </div>

      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-slate-500">Failed</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ failedCount }}</p>
          </div>
          <span class="rounded-full bg-red-100 p-3 text-red-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </span>
        </div>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Recent Reports Table -->
      <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 p-5">
          <h2 class="text-lg font-semibold text-slate-900">Recent Reports</h2>
          <router-link :to="{ name: 'reports' }" class="text-sm font-medium text-teal-700 hover:underline">
            View all
          </router-link>
        </div>

        <div v-if="reports.loading" class="p-8 text-center text-sm text-slate-500">
          Loading…
        </div>
        <div
          v-else-if="recent.length === 0"
          class="p-10 text-center text-sm text-slate-500"
        >
          <p class="text-base font-medium text-slate-700">No reports yet</p>
          <p class="mt-1">Upload your first report to see a plain-language explanation.</p>
          <router-link
            :to="{ name: 'reports.upload' }"
            class="mt-4 inline-block rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700"
          >
            Upload your first report
          </router-link>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Report Name</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Date</th>
                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500">Status</th>
                <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="doc in recent" :key="doc.id" class="hover:bg-slate-50">
                <td class="px-5 py-4 text-sm font-medium text-slate-900">{{ doc.original_filename }}</td>
                <td class="px-5 py-4 text-sm text-slate-500">{{ new Date(doc.created_at).toLocaleDateString() }}</td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="{
                      'bg-emerald-100 text-emerald-700': doc.status === 'processed',
                      'bg-amber-100 text-amber-700': doc.status === 'processing' || doc.status === 'uploaded',
                      'bg-red-100 text-red-700': doc.status === 'failed',
                    }"
                  >
                    {{ doc.status.charAt(0).toUpperCase() + doc.status.slice(1) }}
                  </span>
                </td>
                <td class="px-5 py-4 text-right">
                  <router-link
                    :to="{ name: 'reports.detail', params: { id: doc.id } }"
                    class="text-sm font-medium text-teal-700 hover:text-teal-900"
                  >
                    View
                  </router-link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-slate-900">Quick Actions</h2>
        <div class="space-y-2">
          <router-link
            :to="{ name: 'reports.upload' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-teal-100 p-2.5 text-teal-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
              </svg>
            </span>
            <div>
              <p class="font-medium">Upload a report</p>
              <p class="text-xs text-slate-500">Add a new medical document</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'reports' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
            </span>
            <div>
              <p class="font-medium">Browse reports</p>
              <p class="text-xs text-slate-500">View all your documents</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'trends' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13l6-6 4 4 8-8" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6" />
              </svg>
            </span>
            <div>
              <p class="font-medium">View trends</p>
              <p class="text-xs text-slate-500">Track lab values over time</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'timeline' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
            <div>
              <p class="font-medium">Health timeline</p>
              <p class="text-xs text-slate-500">See your history at a glance</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'assistant' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
              </svg>
            </span>
            <div>
              <p class="font-medium">Ask the assistant</p>
              <p class="text-xs text-slate-500">Get educational answers</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'profile' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            </span>
            <div>
              <p class="font-medium">View profile</p>
              <p class="text-xs text-slate-500">Manage your account</p>
            </div>
          </router-link>
          <router-link
            :to="{ name: 'settings' }"
            class="flex items-center gap-3 rounded-lg border border-slate-200 p-4 text-sm font-medium text-slate-700 transition-colors hover:border-teal-400 hover:bg-teal-50 hover:text-teal-700"
          >
            <span class="rounded-md bg-slate-100 p-2.5 text-slate-700">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <div>
              <p class="font-medium">Settings</p>
              <p class="text-xs text-slate-500">Configure preferences</p>
            </div>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>