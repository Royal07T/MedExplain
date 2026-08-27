<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchSuperAdminAIUsage, type SuperAdminAIUsage } from '@/api/superadmin'

const data = ref<SuperAdminAIUsage | null>(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    data.value = await fetchSuperAdminAIUsage()
  } catch {
    error.value = 'Failed to load AI usage data'
  } finally {
    loading.value = false
  }
})

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount)
}

function maxQueries(): number {
  if (!data.value) return 1
  return Math.max(...data.value.daily.map(d => d.queries), 1)
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">AI Usage</h1>

    <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else-if="data">
      <!-- Summary Stats -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Queries</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.totals.queries }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Total Cost</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ formatCurrency(data.totals.cost) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-sm font-medium text-slate-500">Avg Latency</p>
          <p class="mt-2 text-3xl font-bold text-slate-900">{{ data.totals.avg_latency }}ms</p>
        </div>
      </div>

      <!-- Daily Usage Chart (simple bar chart) -->
      <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900 mb-4">Daily Queries (Last 30 Days)</h2>
        <div class="flex items-end gap-1 h-40">
          <div
            v-for="(day, i) in data.daily"
            :key="i"
            class="flex-1 bg-teal-500 rounded-t min-h-[2px]"
            :style="{ height: `${(day.queries / maxQueries()) * 100}%` }"
            :title="`${day.date}: ${day.queries} queries`"
          />
        </div>
        <div class="mt-2 flex justify-between text-xs text-slate-500">
          <span>{{ data.daily[0]?.date }}</span>
          <span>{{ data.daily[data.daily.length - 1]?.date }}</span>
        </div>
      </div>

      <!-- Daily Breakdown Table -->
      <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
          <h2 class="text-lg font-semibold text-slate-900">Daily Breakdown</h2>
        </div>
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Queries</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Cost</th>
              <th class="px-5 py-3 text-left text-xs font-medium text-slate-500 uppercase">Avg Latency</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="(day, i) in data.daily.slice().reverse()" :key="i" class="hover:bg-slate-50">
              <td class="px-5 py-3 text-sm text-slate-900">{{ day.date }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ day.queries }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ formatCurrency(day.cost) }}</td>
              <td class="px-5 py-3 text-sm text-slate-600">{{ day.avg_latency }}ms</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
