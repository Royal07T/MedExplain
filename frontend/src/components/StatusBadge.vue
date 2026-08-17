<script setup lang="ts">
import { computed } from 'vue'

import type { DocumentStatus } from '@/types'

const props = defineProps<{ status: DocumentStatus }>()

const config: Record<DocumentStatus, { label: string; classes: string }> = {
  uploaded: { label: 'Uploaded', classes: 'bg-slate-100 text-slate-600' },
  processing: { label: 'Processing', classes: 'bg-amber-100 text-amber-700' },
  processed: { label: 'Processed', classes: 'bg-emerald-100 text-emerald-700' },
  failed: { label: 'Failed', classes: 'bg-red-100 text-red-700' },
}

const current = computed(() => config[props.status])
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="current.classes"
  >
    {{ current.label }}
  </span>
</template>