<script setup lang="ts">
import { computed } from 'vue'

import StatusBadge from '@/components/StatusBadge.vue'
import type { Document } from '@/types'

const props = defineProps<{ document: Document }>()
const emit = defineEmits<{ delete: [id: number] }>()

const sizeLabel = computed(() => {
  const kb = props.document.file_size / 1024
  return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${Math.round(kb)} KB`
})

const typeLabel = computed(() => props.document.document_type.replace('_', ' '))

const uploadedLabel = computed(() =>
  new Date(props.document.created_at).toLocaleDateString(),
)
</script>

<template>
  <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <router-link
          :to="{ name: 'reports.detail', params: { id: document.id } }"
          class="truncate font-medium text-slate-900 hover:text-teal-700"
        >
          {{ document.original_filename }}
        </router-link>
        <p class="mt-1 text-sm text-slate-500">
          {{ typeLabel }} &middot; {{ sizeLabel }} &middot; {{ uploadedLabel }}
        </p>
      </div>
      <StatusBadge :status="document.status" />
    </div>

    <p v-if="document.error_message" class="mt-2 text-sm text-red-600">
      {{ document.error_message }}
    </p>

    <div class="mt-3 flex gap-3 text-sm">
      <router-link
        :to="{ name: 'reports.detail', params: { id: document.id } }"
        class="text-teal-700 hover:underline"
      >
        View
      </router-link>
      <button
        type="button"
        class="text-slate-500 hover:text-red-600"
        @click="emit('delete', document.id)"
      >
        Delete
      </button>
    </div>
  </article>
</template>