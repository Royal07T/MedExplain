<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string
    value: string | number
    icon?: string
    trend?: { value: string; positive: boolean }
    color?: 'teal' | 'emerald' | 'amber' | 'red' | 'slate'
  }>(),
  {
    color: 'teal',
  },
)

const colorClasses: Record<string, { bg: string; text: string }> = {
  teal: { bg: 'bg-teal-100', text: 'text-teal-700' },
  emerald: { bg: 'bg-emerald-100', text: 'text-emerald-700' },
  amber: { bg: 'bg-amber-100', text: 'text-amber-700' },
  red: { bg: 'bg-red-100', text: 'text-red-700' },
  slate: { bg: 'bg-slate-100', text: 'text-slate-700' },
}
</script>

<template>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500">{{ label }}</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ value }}</p>
        <p v-if="trend" class="mt-1 text-xs" :class="trend.positive ? 'text-emerald-600' : 'text-red-600'">
          {{ trend.value }}
        </p>
      </div>
      <span v-if="icon" class="rounded-full p-3" :class="[colorClasses[color].bg, colorClasses[color].text]">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
        </svg>
      </span>
    </div>
  </div>
</template>
