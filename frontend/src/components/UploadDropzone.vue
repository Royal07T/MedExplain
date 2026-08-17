<script setup lang="ts">
import { ref } from 'vue'

const ACCEPTED = ['application/pdf', 'image/jpeg', 'image/png']
const MAX_MB = 10

const emit = defineEmits<{ select: [file: File] }>()

const dragging = ref(false)
const error = ref<string | null>(null)

function validate(file: File): boolean {
  error.value = null
  if (!ACCEPTED.includes(file.type)) {
    error.value = 'Only PDF, JPEG, or PNG files are supported.'
    return false
  }
  if (file.size > MAX_MB * 1024 * 1024) {
    error.value = `File must be ${MAX_MB} MB or smaller.`
    return false
  }
  return true
}

function onFile(file: File | undefined) {
  if (!file) return
  if (validate(file)) {
    emit('select', file)
  }
}

function onDrop(event: DragEvent) {
  dragging.value = false
  onFile(event.dataTransfer?.files?.[0])
}

function onChange(event: Event) {
  const input = event.target as HTMLInputElement
  onFile(input.files?.[0])
  input.value = ''
}
</script>

<template>
  <div>
    <label
      class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-10 text-center transition"
      :class="dragging ? 'border-teal-500 bg-teal-50' : 'border-slate-300 bg-white hover:border-teal-400'"
      @dragenter.prevent="dragging = true"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <input
        type="file"
        class="hidden"
        accept=".pdf,.jpg,.jpeg,.png"
        @change="onChange"
      />
      <span
        class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 text-xl text-teal-700"
      >
        PDF
      </span>
      <span class="font-medium text-slate-700">Drag and drop your medical report here</span>
      <span class="text-sm text-slate-500">
        or click to browse &mdash; PDF, JPG or PNG, up to {{ MAX_MB }} MB
      </span>
    </label>
    <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
  </div>
</template>