<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import * as documentsApi from '@/api/documents'
import Disclaimer from '@/components/Disclaimer.vue'
import UploadDropzone from '@/components/UploadDropzone.vue'

const router = useRouter()

const selected = ref<File | null>(null)
const uploading = ref(false)
const progress = ref(0)
const error = ref<string | null>(null)

async function submit() {
  if (!selected.value) return
  uploading.value = true
  progress.value = 0
  error.value = null
  try {
    const doc = await documentsApi.uploadDocument(selected.value, (p) => {
      progress.value = p
    })
    router.push({ name: 'reports.detail', params: { id: doc.id } })
  } catch (err: any) {
    error.value = err.message || 'Upload failed. Please try again.'
    uploading.value = false
  }
}

function reset() {
  selected.value = null
  error.value = null
  progress.value = 0
}
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold text-slate-900">Upload a medical report</h1>

    <UploadDropzone v-if="!selected" @select="(file) => (selected = file)" />

    <div
      v-else
      class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
    >
      <p class="text-sm text-slate-700">
        <strong>Selected file:</strong> {{ selected.name }}
      </p>

      <div v-if="uploading" class="space-y-1">
        <div class="h-2 overflow-hidden rounded bg-slate-200">
          <div
            class="h-full bg-teal-600 transition-all"
            :style="{ width: `${progress}%` }"
          />
        </div>
        <p class="text-xs text-slate-500">{{ progress }}%</p>
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex gap-3">
        <button
          type="button"
          :disabled="uploading"
          class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 disabled:opacity-60"
          @click="submit"
        >
          {{ uploading ? 'Uploading…' : 'Upload and analyze' }}
        </button>
        <button
          type="button"
          :disabled="uploading"
          class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700"
          @click="reset"
        >
          Choose another file
        </button>
      </div>
    </div>

    <Disclaimer />
  </div>
</template>