<script setup lang="ts">
import { computed } from 'vue'

import type { AnalysisItem, AnalysisItemCategory } from '@/types'

const props = defineProps<{ item: AnalysisItem }>()

const labels: Record<AnalysisItemCategory, string> = {
  fact: 'Fact',
  reference_comparison: 'Reference comparison',
  education: 'Education',
  possible_context: 'Possible context',
  question_for_professional: 'Question for your clinician',
}

const accent = computed(() => {
  switch (props.item.category) {
    case 'reference_comparison':
      return 'border-teal-200 bg-teal-50'
    case 'possible_context':
      return 'border-amber-200 bg-amber-50'
    case 'question_for_professional':
      return 'border-sky-200 bg-sky-50'
    default:
      return 'border-slate-200 bg-slate-50'
  }
})
</script>

<template>
  <article class="rounded-lg border p-4" :class="accent">
    <div class="flex items-center justify-between gap-2">
      <h4 class="font-semibold text-slate-900">{{ item.test_name }}</h4>
      <span class="text-xs text-slate-500">
        {{ labels[item.category] ?? item.category }}
      </span>
    </div>
    <p class="mt-2 text-sm text-slate-700">{{ item.explanation }}</p>
  </article>
</template>